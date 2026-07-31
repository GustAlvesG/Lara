<?php

namespace App\Http\Controllers\Freelancer;

use App\Exceptions\CoordinatorAuthorizationException;
use App\Exceptions\FreelancerServiceLockedException;
use App\Exceptions\SpreadsheetImportException;
use App\Http\Controllers\Concerns\AuthorizesCommercialCoordinator;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Freelancer\Concerns\ServesSignatureImages;
use App\Http\Requests\ImportSpreadsheetRequest;
use App\Http\Requests\StoreFreelancerServiceRequest;
use App\Http\Requests\StoreFreelancerServicesBulkRequest;
use App\Http\Requests\UpdateFreelancerServiceRequest;
use App\Imports\FreelancerServiceImport;
use App\Models\Freelancer;
use App\Models\FreelancerService;
use App\Models\FunctionFreelancer;
use App\Models\Status;
use App\Services\FreelancerService as FreelancerServiceManager;
use App\Services\WeeklyLimitCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ServiceController extends Controller
{
    use AuthorizesCommercialCoordinator;
    use ServesSignatureImages;

    public function __construct(private FreelancerServiceManager $freelancerService)
    {
    }

    /** Imagem da assinatura, protegida pela sessão do painel. */
    public function signatureImage(FreelancerService $freelancerService, string $party)
    {
        return $this->signatureImageResponse($freelancerService, $party);
    }

    public function index()
    {
        $services = FreelancerService::with(['freelancer', 'functionFreelancer', 'status'])
            ->orderByDesc('start_date')
            ->get();

        $excessFlags = FreelancerService::flagExcessWithinCollection($services);

        return view('freelancer.services.index', compact('services', 'excessFlags'));
    }

    public function create(FreelancerServiceImport $import)
    {
        return view('freelancer.services.create', array_merge($this->formOptions(), [
            'importColumns' => $import->columns(),
        ]));
    }

    public function store(StoreFreelancerServiceRequest $request)
    {
        $data = $request->validated();

        // Contrato não é gerado enquanto o cadastro do freelancer estiver
        // incompleto: o formulário volta preenchido apontando os dados que faltam.
        $freelancer = Freelancer::find($data['freelancer_id']);

        if ($freelancer && !$freelancer->hasCompleteContractData()) {
            return back()
                ->withInput()
                ->with('error', $this->incompleteFreelancerMessage($freelancer));
        }

        // O aviso de limite semanal vem antes de gravar: o formulário volta
        // preenchido pedindo confirmação e, junto com ela, a matrícula e o PIN
        // do coordenador do Comercial — quem registra o contrato não se
        // autoriza sozinho a passar do limite.
        if (FreelancerService::wouldExceedWeeklyLimit($data['freelancer_id'], $data['start_date'])) {
            if (!$request->boolean('confirm_weekly_limit')) {
                return $this->backToWeeklyLimitConfirmation($request, $data);
            }

            try {
                $coordinator = $this->authorizeCommercialCoordinator(
                    $request->input('coordinator_matricula'),
                    $request->input('coordinator_pin'),
                    (int) $data['freelancer_id'],
                    $data['start_date'],
                );
            } catch (CoordinatorAuthorizationException $e) {
                return $this->backToWeeklyLimitConfirmation($request, $data)
                    ->with('error', $e->getMessage());
            }

            $data['weekly_limit_authorized_by'] = $coordinator->id;
            $data['weekly_limit_authorized_at'] = now();
        }

        $service = $this->freelancerService->createService($data);

        return redirect()->route('freelancer-services.show', $service)
            ->with('success', 'Serviço registrado com sucesso.');
    }

    /* ---------------------------------------------------------------------
     | Registro em massa (pelo painel, sem planilha)
     |---------------------------------------------------------------------*/

    public function bulkCreate()
    {
        return view('freelancer.services.bulk', array_merge($this->formOptions(), [
            'maxRows' => StoreFreelancerServicesBulkRequest::MAX_ROWS,
        ]));
    }

    /**
     * Grava o lote inteiro ou nenhum. É a mesma escolha da importação por
     * planilha: no meio-termo, metade entra e o reenvio duplica o resto.
     *
     * As linhas que passam do limite de 7 dias contam umas com as outras — três
     * linhas do mesmo freelancer na mesma semana estouram, ainda que ele não
     * tenha nada no banco. Passando do limite, o lote todo só grava com a
     * liberação do coordenador do Comercial (PIN ou código de e-mail), pedida
     * uma vez para o envio.
     */
    public function bulkStore(StoreFreelancerServicesBulkRequest $request)
    {
        $rows = array_values($request->validated()['services']);
        $exceeding = FreelancerService::rowsExceedingWeeklyLimit($rows);

        $authorization = [];

        if ($exceeding) {
            if (!$request->boolean('confirm_weekly_limit')) {
                return back()
                    ->withInput($request->except('coordinator_pin'))
                    ->with('confirm_weekly_limit', $this->bulkWeeklyLimitMessage($exceeding));
            }

            try {
                // O código de e-mail é preso a um contrato; num lote com várias
                // linhas ele não serve, e a liberação é pelo PIN. Com uma linha
                // só, o código daquela linha vale.
                $single = count($exceeding) === 1 && count($rows) === 1 ? $rows[0] : null;

                $coordinator = $this->authorizeCommercialCoordinator(
                    $request->input('coordinator_matricula'),
                    $request->input('coordinator_pin'),
                    $single ? (int) $single['freelancer_id'] : null,
                    $single['start_date'] ?? null,
                );
            } catch (CoordinatorAuthorizationException $e) {
                return back()
                    ->withInput($request->except('coordinator_pin'))
                    ->with('confirm_weekly_limit', $this->bulkWeeklyLimitMessage($exceeding))
                    ->with('error', $e->getMessage());
            }

            $authorization = [
                'weekly_limit_authorized_by' => $coordinator->id,
                'weekly_limit_authorized_at' => now(),
            ];
        }

        $exceedingRows = array_flip($exceeding);

        $created = DB::transaction(function () use ($rows, $exceedingRows, $authorization) {
            foreach ($rows as $index => $row) {
                // A autorização fica gravada só nas linhas que a exigiram.
                $this->freelancerService->createService(
                    isset($exceedingRows[$index]) ? $row + $authorization : $row
                );
            }

            return count($rows);
        });

        return redirect()->route('freelancer-services.index')
            ->with('success', $created . ' serviço(s) registrado(s) com sucesso.');
    }

    /** @param  array<int, int>  $exceeding  índices das linhas */
    private function bulkWeeklyLimitMessage(array $exceeding): string
    {
        $lines = implode(', ', array_map(fn(int $index) => $index + 1, $exceeding));

        return count($exceeding) === 1
            ? 'A linha ' . $lines . ' passa do limite de ' . FreelancerService::WEEKLY_LIMIT
                . ' serviços em ' . FreelancerService::WEEKLY_WINDOW_DAYS . ' dias. Para gravar o lote, o '
                . 'coordenador do setor ' . self::COORDINATOR_SECTOR . ' precisa liberar.'
            : 'As linhas ' . $lines . ' passam do limite de ' . FreelancerService::WEEKLY_LIMIT
                . ' serviços em ' . FreelancerService::WEEKLY_WINDOW_DAYS . ' dias. Para gravar o lote, o '
                . 'coordenador do setor ' . self::COORDINATOR_SECTOR . ' precisa liberar.';
    }

    /**
     * Manda por e-mail o código de liberação ao coordenador do Comercial, para
     * quando ele não pode vir digitar o PIN. O botão fica no próprio formulário
     * (via `formaction`), então tudo que já foi preenchido volta intacto — só
     * muda a mensagem no topo do bloco de liberação.
     */
    public function sendWeeklyLimitCode(Request $request, WeeklyLimitCodeService $codes)
    {
        $data = $request->validate([
            'coordinator_matricula' => ['required', 'string'],
            'freelancer_id' => ['required', 'integer', 'exists:freelancers,id'],
            'start_date' => ['required', 'date'],
        ], [], ['coordinator_matricula' => 'matrícula do coordenador']);

        try {
            $coordinator = $this->findCommercialCoordinator($data['coordinator_matricula']);

            $code = $codes->issue(
                $coordinator,
                (int) $data['freelancer_id'],
                Carbon::parse($data['start_date'])->toDateString(),
                auth()->user(),
            );
        } catch (CoordinatorAuthorizationException $e) {
            return $this->backToWeeklyLimitConfirmation($request, $data)->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            // Falha de SMTP não pode passar por "código enviado": quem está no
            // balcão ficaria esperando um e-mail que não saiu.
            report($e);

            return $this->backToWeeklyLimitConfirmation($request, $data)
                ->with('error', 'Não foi possível enviar o e-mail com o código. Verifique o servidor de e-mail e tente novamente.');
        }

        return $this->backToWeeklyLimitConfirmation($request, $data)->with(
            'success',
            'Código enviado para ' . WeeklyLimitCodeService::maskEmail($code->sent_to) . '. '
                . 'Vale até ' . $code->expires_at->format('H:i') . '. Peça que o coordenador dite o número.'
        );
    }

    /** Arquivo .xlsx em branco, no formato aceito pela importação. */
    public function importTemplate(FreelancerServiceImport $import)
    {
        return $import->downloadTemplate();
    }

    public function import(ImportSpreadsheetRequest $request, FreelancerServiceImport $import)
    {
        try {
            $result = $import->import($request->file('spreadsheet')->getRealPath());
        } catch (SpreadsheetImportException $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($result['errors']) {
            return back()
                ->with('error', 'Nenhum serviço foi importado. Corrija a planilha e envie novamente.')
                ->with('import_errors', $result['errors']);
        }

        $redirect = redirect()->route('freelancer-services.index')
            ->with('success', $result['imported'] . ' serviço(s) importado(s) com sucesso.');

        // O mesmo alerta do cadastro individual, resumido para o lote.
        $exceeding = $result['records']
            ->filter(fn(FreelancerService $service) => $service->exceedsWeeklyLimit())
            ->map(fn(FreelancerService $service) => $service->freelancer->name)
            ->unique()
            ->values();

        if ($exceeding->isNotEmpty()) {
            $redirect->with('warning', 'Acima do limite recomendado de '
                . FreelancerService::WEEKLY_LIMIT . ' serviços em 7 dias: ' . $exceeding->implode(', ') . '.');
        }

        return $redirect;
    }

    public function show(FreelancerService $freelancerService)
    {
        $freelancerService->load([
            'freelancer',
            'functionFreelancer',
            'status',
            'freelancerSignedBy',
            'coordinatorSignedBy',
            'paidBy',
            'cancelledBy',
            'createdBy',
            'updatedBy',
            'weeklyLimitAuthorizedBy',
        ]);

        return view('freelancer.services.show', array_merge($this->formOptions(), [
            'service' => $freelancerService,
            'exceedsWeeklyLimit' => $freelancerService->exceedsWeeklyLimit(),
            // Assinar como coordenador não é mais atribuição do painel: só o
            // traço desenhado no tablet vale (ver KioskController).
            'canCancel' => $this->isCoordinator() && $freelancerService->canBeCancelled(),
        ]));
    }

    /**
     * Documento do contrato (modelo do Clube dos Funcionários) preenchido com
     * os dados do serviço e com a imagem da assinatura do freelancer, quando
     * houver. Página imprimível/salvável em PDF pelo navegador.
     */
    public function document(FreelancerService $freelancerService)
    {
        $freelancerService->load([
            'freelancer',
            'functionFreelancer',
            'freelancerSignedBy',
            'coordinatorSignedBy',
        ]);

        // Barra a geração do documento para cadastros incompletos — cobre também
        // contratos legados, criados antes desta trava.
        if ($freelancerService->freelancer && !$freelancerService->freelancer->hasCompleteContractData()) {
            return redirect()->route('freelancer-services.show', $freelancerService)
                ->with('error', $this->incompleteFreelancerMessage($freelancerService->freelancer));
        }

        return view('freelancer.services.document', ['service' => $freelancerService]);
    }

    public function update(UpdateFreelancerServiceRequest $request, FreelancerService $freelancerService)
    {
        try {
            $this->freelancerService->updateService($freelancerService, $request->validated());
        } catch (FreelancerServiceLockedException $e) {
            return redirect()->route('freelancer-services.show', $freelancerService)
                ->with('error', $e->getMessage());
        }

        $redirect = redirect()->route('freelancer-services.show', $freelancerService)
            ->with('success', 'Serviço atualizado com sucesso.');

        if ($freelancerService->exceedsWeeklyLimit()) {
            $redirect->with('warning', $this->weeklyLimitMessage($freelancerService));
        }

        return $redirect;
    }

    /**
     * Cancela o contrato — permitido apenas enquanto não houver assinaturas.
     */
    public function cancel(FreelancerService $freelancerService)
    {
        abort_unless($this->isCoordinator(), 403);

        try {
            $this->freelancerService->cancelService($freelancerService, auth()->user());
        } catch (FreelancerServiceLockedException $e) {
            return redirect()->route('freelancer-services.show', $freelancerService)
                ->with('error', $e->getMessage());
        }

        return redirect()->route('freelancer-services.show', $freelancerService)
            ->with('success', 'Contrato cancelado.');
    }

    public function destroy(FreelancerService $freelancerService)
    {
        if (!$freelancerService->canBeDeleted()) {
            return redirect()->route('freelancer-services.show', $freelancerService)
                ->with('error', 'Contrato assinado não pode ser excluído.');
        }

        $freelancerService->delete();

        return redirect()->route('freelancer-services.index')
            ->with('success', 'Serviço excluído com sucesso.');
    }

    private function formOptions(): array
    {
        return [
            // Os campos do contrato vêm junto para o formulário sinalizar (e
            // desabilitar) freelancers com cadastro incompleto no seletor.
            'freelancers' => Freelancer::orderBy('name')
                ->get(array_merge(['id', 'name'], Freelancer::CONTRACT_REQUIRED_FIELDS)),
            'functions' => FunctionFreelancer::orderBy('name')->get(['id', 'name', 'price']),
            'statuses' => Status::orderBy('status')->get(),
        ];
    }

    private function isCoordinator(): bool
    {
        return auth()->user()?->isCoordinator() ?? false;
    }

    /**
     * Devolve o formulário no estado "aguardando a liberação do coordenador".
     * O PIN digitado NÃO volta para a tela: sai do input reenviado, para não
     * ficar guardado na sessão nem repopular o campo.
     */
    private function backToWeeklyLimitConfirmation(Request $request, array $data)
    {
        return back()
            ->withInput($request->except('coordinator_pin'))
            ->with('confirm_weekly_limit', $this->weeklyLimitConfirmMessage($data));
    }

    /**
     * Mensagem do aviso exibido antes da gravação, contando o serviço que está
     * prestes a ser criado.
     */
    private function weeklyLimitConfirmMessage(array $data): string
    {
        $freelancer = Freelancer::find($data['freelancer_id']);
        $total = FreelancerService::countInWeeklyWindow($data['freelancer_id'], $data['start_date']) + 1;

        return 'Com este registro, ' . ($freelancer?->name ?? 'o freelancer') . ' passa a ter ' . $total
            . ' serviços numa janela de ' . FreelancerService::WEEKLY_WINDOW_DAYS
            . ' dias (limite: ' . FreelancerService::WEEKLY_LIMIT . '). Para prosseguir, o coordenador do setor '
            . self::COORDINATOR_SECTOR . ' precisa liberar com a matrícula e o PIN dele.';
    }

    /** Mensagem padrão quando o cadastro do freelancer impede gerar o contrato. */
    private function incompleteFreelancerMessage(Freelancer $freelancer): string
    {
        return 'Cadastro de ' . $freelancer->name . ' incompleto: faltam '
            . implode(', ', $freelancer->missingContractFieldLabels())
            . '. Complete o cadastro do freelancer antes de gerar o contrato.';
    }

    private function weeklyLimitMessage(FreelancerService $service): string
    {
        return 'Atenção: ' . $service->freelancer->name . ' já possui mais de '
            . FreelancerService::WEEKLY_LIMIT . ' serviços registrados numa janela de 7 dias '
            . '(limite recomendado: ' . FreelancerService::WEEKLY_LIMIT . ').';
    }
}
