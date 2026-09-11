<?php

namespace App\Http\Controllers\Freelancer;

use App\Exceptions\FreelancerServiceLockedException;
use App\Http\Controllers\Controller;
use App\Models\FreelancerService;
use App\Services\FreelancerService as FreelancerServiceManager;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Validação dos contratos da redação 2 pelo coordenador do setor Comercial.
 *
 * Na redação 2 a coordenação não assina o documento — quem assina pelo
 * CONTRATANTE é o diretor, na aprovação do lote. O que o coordenador faz é
 * VALIDAR: confirmar, contrato a contrato, que o serviço aconteceu como está
 * escrito. Validado, o contrato fica disponível para a montagem de lote.
 *
 * O desenho é o de uma leitura, não o de uma lista de aprovação:
 *
 * - a fila não tem seleção nem ação em massa — cada linha só abre o contrato;
 * - a tela do contrato mostra o documento inteiro, e o bloco da validação só
 *   é liberado quando a rolagem chega ao fim dele;
 * - cada validação pede o PIN do coordenador (o mesmo do tablet);
 * - o POST recebe UM contrato, pela rota, e exige a marca que a abertura da
 *   tela gravou na sessão para aquele contrato. Validar em série, sem abrir
 *   as páginas, esbarra nela.
 *
 * A rolagem até o fim é conferida no navegador — o servidor não tem como
 * prová-la. O que o servidor garante é que cada validação passou pela tela
 * daquele contrato, um de cada vez, com o PIN digitado.
 */
class ValidationController extends Controller
{
    /** Contratos por página na fila. */
    private const PER_PAGE = 30;

    /** Chave da sessão onde fica a marca de abertura de cada contrato. */
    private const SESSION_KEY = 'freelancer_validation';

    public function __construct(private FreelancerServiceManager $freelancerService)
    {
    }

    /** Fila: redação 2, assinados pelo freelancer, liberados e sem validação. */
    public function index()
    {
        $services = FreelancerService::awaitingCoordinatorValidation()
            ->with(['freelancer:id,name,cpf', 'functionFreelancer', 'baseService'])
            // Os mais antigos primeiro: são os que travam o lote e o pagamento.
            ->orderBy('freelancer_signed_at')
            ->paginate(self::PER_PAGE);

        // Assinados hoje ainda esperam a manhã seguinte; não entram na fila, mas
        // a contagem evita a pergunta "cadê o contrato de ontem à noite?".
        $awaitingRelease = FreelancerService::awaitingRelease()
            ->contractorSignedBy(FreelancerService::CONTRACTOR_SIGNS_DIRECTOR)
            ->count();

        return view('freelancer.validation.index', compact('services', 'awaitingRelease'));
    }

    /**
     * O contrato inteiro, com a validação ao pé. Abrir a tela grava a marca que
     * o POST exige — nova a cada abertura, e consumida na tentativa.
     */
    public function show(Request $request, FreelancerService $freelancerService)
    {
        $freelancerService->load([
            'freelancer',
            'functionFreelancer',
            'freelancerSignedBy',
            'coordinatorSignedBy',
            'director',
            'baseService.functionFreelancer',
        ]);

        $blockReason = $freelancerService->coordinatorValidationBlockReason();

        if ($blockReason === null && $freelancerService->freelancer
            && !$freelancerService->freelancer->hasCompleteContractData()) {
            $blockReason = 'Cadastro do freelancer incompleto. Complete os dados antes de validar o contrato.';
        }

        $token = null;

        if ($blockReason === null) {
            $token = Str::random(40);
            $request->session()->put($this->sessionKey($freelancerService), $token);
        }

        return view('freelancer.validation.show', [
            'service' => $freelancerService,
            'blockReason' => $blockReason,
            'token' => $token,
            'hasPin' => $request->user()->hasPin(),
            // Quantos ainda esperam, para o coordenador saber quanto falta.
            'remaining' => FreelancerService::awaitingCoordinatorValidation()
                ->where('id', '!=', $freelancerService->id)
                ->count(),
        ]);
    }

    public function store(Request $request, FreelancerService $freelancerService)
    {
        $request->validate([
            'pin' => ['required', 'digits:6'],
            'read_token' => ['required', 'string'],
            // Marcado pelo navegador quando a rolagem chega ao fim do documento.
            'read_to_end' => ['accepted'],
        ], [
            'read_to_end.accepted' => 'Leia o contrato até o fim antes de validar.',
        ], [
            'pin' => 'PIN',
        ]);

        $voltar = redirect()->route('freelancer-validation.show', $freelancerService);

        // A marca vale uma tentativa: acertando ou errando o PIN, a próxima
        // exige abrir a tela de novo — que é para onde o erro já devolve.
        $esperado = $request->session()->pull($this->sessionKey($freelancerService));

        if (!is_string($esperado) || !hash_equals($esperado, (string) $request->input('read_token'))) {
            return $voltar->with('error', 'Abra o contrato e leia até o fim antes de validar.');
        }

        $coordinator = $request->user();

        if (!$coordinator->hasPin()) {
            return $voltar->with('error', 'Você ainda não tem PIN definido. Peça para defini-lo na tela de Usuários.');
        }

        if (!$coordinator->checkPin($request->input('pin'))) {
            return $voltar->with('error', 'PIN inválido.');
        }

        try {
            $this->freelancerService->validateAsCoordinator($freelancerService, $coordinator);
        } catch (FreelancerServiceLockedException $e) {
            return $voltar->with('error', $e->getMessage());
        }

        return redirect()->route('freelancer-validation.index')->with(
            'success',
            ($freelancerService->isAmendment() ? 'Termo' : 'Contrato') . " #{$freelancerService->id} de "
                . ($freelancerService->freelancer?->name ?? 'freelancer')
                . ' validado. Ele já pode entrar num lote.'
        );
    }

    private function sessionKey(FreelancerService $service): string
    {
        return self::SESSION_KEY . '.' . $service->id;
    }
}
