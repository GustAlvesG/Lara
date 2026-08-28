<?php

namespace App\Http\Controllers\Freelancer;

use App\Exceptions\CoordinatorAuthorizationException;
use App\Exceptions\FreelancerBatchException;
use App\Exceptions\FreelancerServiceLockedException;
use App\Exceptions\SalesReportException;
use App\Http\Controllers\Concerns\AuthorizesCommercialCoordinator;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Freelancer\Concerns\ServesSignatureImages;
use App\Http\Requests\StoreFreelancerRequest;
use App\Http\Requests\StoreFreelancerServiceAmendmentRequest;
use App\Http\Requests\StoreFreelancerServiceRequest;
use App\Http\Requests\StoreSalesCommissionRequest;
use App\Http\Requests\UpdateFreelancerPixKeyRequest;
use App\Http\Requests\UpdateFreelancerRequest;
use App\Models\Freelancer;
use App\Models\FreelancerService;
use App\Models\FreelancerServiceBatch;
use App\Models\FunctionFreelancer;
use App\Models\User;
use App\Services\FreelancerBatchService;
use App\Services\FreelancerService as FreelancerServiceManager;
use App\Services\MultiVendasSalesReport;
use App\Services\WeeklyLimitCodeService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Kiosk de assinatura de contratos de freelancer, para uso em tablet — substitui
 * o bot do Telegram por uma interface de toque.
 *
 * Autenticação PRÓPRIA, independente da sessão web: quem opera entra com
 * matrícula + PIN de 6 dígitos e recebe uma sessão de kiosk (guardada no lado
 * do servidor). O PIN é reconfirmado a cada assinatura — espelhando a
 * reconfirmação de senha que o bot fazia pela API.
 *
 * São dois modos, decididos pelo que o usuário é:
 *
 *  - `operator`    — quem tem a permissão `manage freelancers`. Cadastra
 *                    freelancers, registra contratos e conduz a assinatura do
 *                    freelancer. Fica gravado em created_by / freelancer_signed_by.
 *                    A sessão dura 30 minutos OU 5 contratos, o que vier primeiro.
 *  - `coordinator` — quem é coordenador do setor Comercial. Vê os contratos que
 *                    o freelancer já assinou e que aguardam a contraparte, e
 *                    assina cada um deles. Só o tempo limita a sessão: um
 *                    coordenador costuma quitar uma fila inteira de contratos.
 *
 * Quem acumula os dois papéis escolhe o modo logo após entrar.
 */
class KioskController extends Controller
{
    use AuthorizesCommercialCoordinator;
    use ServesSignatureImages;

    private const SESSION_MINUTES = 30;
    private const SESSION_MAX_CONTRACTS = 5;

    // COORDINATOR_SECTOR ('Comercial') vem de AuthorizesCommercialCoordinator:
    // é o mesmo setor que assina contratos e que libera o limite semanal.

    /** Quantos contratos pendentes a fila do coordenador traz por vez. */
    private const COORDINATOR_QUEUE_LIMIT = 50;

    /** Por quantos dias o tablet ainda oferece a comissão de um turno. */
    private const COMMISSION_WINDOW_DAYS = 7;

    private const MODE_OPERATOR = 'operator';
    private const MODE_COORDINATOR = 'coordinator';

    public function __construct(
        private FreelancerServiceManager $freelancerService,
        private FreelancerBatchService $batches,
    ) {
    }

    public function index()
    {
        return view('kiosk.index');
    }

    /* ---------------------------------------------------------------------
     | Autenticação do operador (matrícula + PIN)
     |---------------------------------------------------------------------*/

    /** Estado atual da sessão — permite retomar ao recarregar a página. */
    public function session()
    {
        if (!$this->sessionActive()) {
            return response()->json(['active' => false]);
        }

        return response()->json([
            'active' => true,
            'operator' => $this->operatorPayload($this->currentOperator()),
            'session' => $this->sessionPayload(),
        ]);
    }

    public function login(Request $request)
    {
        $request->validate([
            'matricula' => ['required', 'string'],
            'pin' => ['required', 'digits:6'],
        ]);

        $user = User::where('matricula', $request->input('matricula'))->first();

        if (!$user || (int) $user->status_id !== 1) {
            return response()->json(['error' => 'Matrícula não encontrada ou usuário inativo.'], 401);
        }

        $modes = $this->availableModes($user);

        if (!$modes) {
            return response()->json(['error' => 'Este usuário não tem acesso ao kiosk.'], 403);
        }

        if (!$user->hasPin()) {
            return response()->json(['error' => 'Usuário sem PIN. Defina um PIN de 6 dígitos no painel (Usuários).'], 422);
        }

        if (!$user->checkPin($request->input('pin'))) {
            return response()->json(['error' => 'PIN inválido.'], 401);
        }

        session([
            'kiosk.operator_id' => $user->id,
            'kiosk.started_at' => now()->timestamp,
            'kiosk.count' => 0,
            // Com um papel só não há o que escolher; com os dois, a tela pergunta.
            'kiosk.mode' => count($modes) === 1 ? $modes[0] : null,
        ]);

        $user->forceFill(['last_login_at' => now()])->save();

        return response()->json([
            'operator' => $this->operatorPayload($user),
            'session' => $this->sessionPayload(),
        ]);
    }

    /** Escolha do modo, quando o usuário é operador e coordenador ao mesmo tempo. */
    public function mode(Request $request)
    {
        $operator = $this->operatorOrFail();

        $request->validate([
            'mode' => ['required', 'in:' . self::MODE_OPERATOR . ',' . self::MODE_COORDINATOR],
        ]);

        $mode = $request->input('mode');

        if (!in_array($mode, $this->availableModes($operator), true)) {
            return response()->json(['error' => 'Este usuário não tem acesso a esse modo.'], 403);
        }

        session(['kiosk.mode' => $mode, 'kiosk.count' => 0]);

        return response()->json([
            'operator' => $this->operatorPayload($operator),
            'session' => $this->sessionPayload(),
        ]);
    }

    public function logout()
    {
        $this->clearSession();

        return response()->json(['ok' => true]);
    }

    /* ---------------------------------------------------------------------
     | Freelancer
     |---------------------------------------------------------------------*/

    public function findFreelancer(string $cpf)
    {
        $this->operatorModeOrFail();

        $freelancer = Freelancer::where('cpf', $cpf)->first();

        if (!$freelancer) {
            return response()->json(['found' => false], 404);
        }

        return response()->json(['found' => true, 'freelancer' => $this->freelancerPayload($freelancer)]);
    }

    public function storeFreelancer(StoreFreelancerRequest $request)
    {
        $operator = $this->operatorModeOrFail();

        $data = $request->validated();
        $data['created_by'] = $operator->id;

        $freelancer = $this->freelancerService->create($data);
        $freelancer->forceFill(['created_by' => $operator->id, 'updated_by' => $operator->id])->save();

        return response()->json(['freelancer' => $this->freelancerPayload($freelancer)], 201);
    }

    /**
     * Completa/atualiza o cadastro do freelancer pelo tablet — o caminho para
     * destravar a geração do contrato quando faltam dados. Mesmas regras
     * lenientes do painel (só nome e CPF obrigatórios).
     */
    public function updateFreelancer(UpdateFreelancerRequest $request, Freelancer $freelancer)
    {
        $operator = $this->operatorModeOrFail();

        $data = $request->validated();
        $data['updated_by'] = $operator->id;

        $freelancer = $this->freelancerService->updateFreelancer($freelancer, $data);

        return response()->json(['freelancer' => $this->freelancerPayload($freelancer)]);
    }

    /**
     * Corrige a chave PIX na conferência que antecede a assinatura — o caminho
     * para quando o freelancer olha a chave no tablet e diz que não é a dele.
     *
     * É um endpoint separado do cadastro de propósito: mudar para onde o
     * dinheiro vai não é a mesma coisa que corrigir um endereço, e a alteração
     * fica registrada em log com autor e horário.
     */
    public function updatePixKey(UpdateFreelancerPixKeyRequest $request, Freelancer $freelancer)
    {
        $operator = $this->operatorModeOrFail();

        $freelancer = $this->freelancerService->updatePixKey(
            $freelancer,
            $request->validated('pix_key_type'),
            $request->validated('pix_key'),
            $operator,
        );

        return response()->json(['freelancer' => $this->freelancerPayload($freelancer)]);
    }

    /* ---------------------------------------------------------------------
     | Funções e serviços
     |---------------------------------------------------------------------*/

    public function functions()
    {
        $this->operatorModeOrFail();

        return response()->json(
            FunctionFreelancer::orderBy('name')->get(['id', 'name', 'price'])
                ->map(fn(FunctionFreelancer $f) => [
                    'id' => $f->id,
                    'name' => $f->name,
                    'price' => (float) $f->price,
                ])
        );
    }

    /**
     * O que o freelancer ainda tem a fazer no tablet: contratos esperando a
     * assinatura dele e contratos já assinados cujo turno pode ter mudado — é
     * neles que se faz o aditivo. O payload diz, por contrato, qual das duas
     * ações está liberada.
     */
    public function services(Freelancer $freelancer)
    {
        $this->operatorModeOrFail();

        $services = $freelancer->freelancerServices()
            // `batch` e `baseService` entram na regra do aditivo; sem eles cada
            // linha da lista viraria duas consultas.
            // `amendments` responde "já tem comissão?" sem uma consulta por linha.
            // `freelancer` entra por causa da chave PIX do payload — sem ela,
            // cada linha da lista faria a sua própria consulta.
            ->with(['functionFreelancer', 'freelancer', 'batch', 'baseService.functionFreelancer', 'amendments'])
            ->orderByDesc('start_date')
            ->get()
            // O jantar entra na lista pelo mesmo motivo dos demais: é coisa que
            // ainda falta fazer neste contrato. Cobre o freelancer que assinou e
            // saiu antes de responder — sem isso, a pergunta não teria segunda
            // chance, e a cozinha ficaria sem o prato dele.
            ->filter(fn(FreelancerService $s) => $s->canBeSignedByFreelancer()
                || $s->canBeAmended()
                || $s->needsDinnerAnswer()
                || ($s->canReceiveCommission() && $this->withinCommissionWindow($s)))
            ->values()
            ->map(fn(FreelancerService $s) => $this->servicePayload($s));

        return response()->json($services);
    }

    /**
     * A comissão é oferecida no tablet só para turnos recentes. A REGRA não
     * expira — um valor de venda pode chegar depois, e vai chegar quando a
     * captura for automática —, mas o tablet é o aparelho do fim do expediente:
     * sem essa janela, todo contrato de garçom já assinado ficaria para sempre
     * na lista de atendimento oferecendo comissão.
     */
    private function withinCommissionWindow(FreelancerService $service): bool
    {
        return $service->start_date !== null
            && Carbon::parse($service->start_date)->startOfDay()
                ->greaterThanOrEqualTo(now()->subDays(self::COMMISSION_WINDOW_DAYS)->startOfDay());
    }

    public function storeService(StoreFreelancerServiceRequest $request)
    {
        $operator = $this->operatorModeOrFail();

        $data = $request->validated();
        $data['created_by'] = $operator->id;

        // Contrato não é gerado enquanto o cadastro estiver incompleto. A tela
        // trata este 422 abrindo o formulário para completar os dados faltantes.
        $freelancer = Freelancer::find($data['freelancer_id']);

        if ($freelancer && !$freelancer->hasCompleteContractData()) {
            return response()->json([
                'error' => 'Cadastro incompleto. Complete os dados do freelancer antes de gerar o contrato.',
                'incomplete_freelancer' => true,
                'freelancer' => $this->freelancerPayload($freelancer),
            ], 422);
        }

        // Limite semanal: primeiro toque devolve 409 pedindo confirmação; o
        // reenvio exige confirm_weekly_limit + a matrícula e o PIN do
        // coordenador do Comercial. Não é o PIN de quem está operando o tablet:
        // o operador não se autoriza a passar do limite.
        if (FreelancerService::wouldExceedWeeklyLimit($data['freelancer_id'], $data['start_date'])) {
            if (!$request->boolean('confirm_weekly_limit')) {
                return response()->json($this->weeklyLimitPayload($data), 409);
            }

            try {
                $coordinator = $this->authorizeCommercialCoordinator(
                    $request->input('coordinator_matricula'),
                    $request->input('coordinator_pin'),
                    (int) $data['freelancer_id'],
                    $data['start_date'],
                );
            } catch (CoordinatorAuthorizationException $e) {
                return response()->json(['error' => $e->getMessage(), 'step' => $e->step], 401);
            }

            // Null quando a liberação veio do código: o mesmo número foi para
            // todos os coordenadores e não há a quem atribuir.
            $data['weekly_limit_authorized_by'] = $coordinator?->id;
            $data['weekly_limit_authorized_at'] = now();
        }

        $service = $this->freelancerService->createService($data);
        $service->forceFill(['created_by' => $operator->id, 'updated_by' => $operator->id])->save();
        $this->bumpCount();

        return response()->json([
            'service' => $this->servicePayload($service->load(['functionFreelancer', 'freelancer'])),
            'session' => $this->sessionPayload(),
        ], 201);
    }

    /**
     * Contrato aditivo: o turno mudou depois de o contrato já estar assinado —
     * esticou, encurtou ou trocou de local. Como contrato assinado não se
     * altera, gera-se um contrato novo que referencia o base e muda só horário
     * de início, de término e local; o base fica marcado como substituído.
     *
     * Não passa pelo limite semanal: o aditivo não acrescenta um dia de
     * trabalho, remenda um turno que já foi contado quando o base foi criado.
     */
    public function storeAmendment(StoreFreelancerServiceAmendmentRequest $request, FreelancerService $freelancerService)
    {
        $operator = $this->operatorModeOrFail();

        // Mesma trava do contrato comum: sem cadastro completo não se gera
        // documento para assinar.
        $freelancer = $freelancerService->freelancer;

        if ($freelancer && !$freelancer->hasCompleteContractData()) {
            return response()->json([
                'error' => 'Cadastro incompleto. Complete os dados do freelancer antes de gerar o aditivo.',
                'incomplete_freelancer' => true,
                'freelancer' => $this->freelancerPayload($freelancer),
            ], 422);
        }

        try {
            $amendment = $this->freelancerService->createAmendment(
                $freelancerService,
                $request->validated(),
                $operator,
            );
        } catch (FreelancerServiceLockedException $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        }

        $this->bumpCount();

        return response()->json([
            'service' => $this->servicePayload(
                $amendment->load(['functionFreelancer', 'freelancer', 'baseService.functionFreelancer'])
            ),
            'session' => $this->sessionPayload(),
        ], 201);
    }

    /**
     * Comissão de venda: o segundo tipo de aditivo, exclusivo das funções
     * marcadas na tela de Funções (hoje, só o Garçom) e assinado ao FINAL do
     * expediente, quando já se sabe quanto foi vendido.
     *
     * Diferente do aditivo de horário, a comissão ACRESCE ao contrato: o turno
     * continua sendo pago pelo contrato base, e este documento é pago por cima.
     * Por isso ela não pede o base fora de lote nem por assinar — só que o
     * freelancer tenha assinado o contrato do turno.
     *
     * Sem limite semanal, pela mesma razão do outro aditivo: nenhum dia novo de
     * trabalho é acrescentado.
     */
    /**
     * Apura as vendas do turno no MultiVendas, para o operador conferir antes de
     * gerar a comissão. Login e período vêm pré-preenchidos pela tela (CPF do
     * freelancer e horário do serviço) e podem ser corrigidos — o login do PDV
     * nem sempre é o CPF, e o caixa pode ter sido fechado fora do horário.
     *
     * Só consulta: nada é gravado aqui. O relatório que vale é o que a gravação
     * refaz, para o anexo do documento não vir do navegador.
     */
    public function salesReport(Request $request, FreelancerService $freelancerService, MultiVendasSalesReport $reports)
    {
        $this->operatorModeOrFail();

        $data = $request->validate([
            'login' => ['required', 'string', 'max:100'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date'],
        ]);

        try {
            $report = $reports->forSeller(
                $data['login'],
                Carbon::parse($data['from']),
                Carbon::parse($data['to']),
            );
        } catch (SalesReportException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['report' => $report]);
    }

    public function storeCommission(
        StoreSalesCommissionRequest $request,
        FreelancerService $freelancerService,
        MultiVendasSalesReport $reports,
    ) {
        $operator = $this->operatorModeOrFail();

        $freelancer = $freelancerService->freelancer;

        if ($freelancer && !$freelancer->hasCompleteContractData()) {
            return response()->json([
                'error' => 'Cadastro incompleto. Complete os dados do freelancer antes de gerar a comissão.',
                'incomplete_freelancer' => true,
                'freelancer' => $this->freelancerPayload($freelancer),
            ], 422);
        }

        $data = $request->validated();

        // O anexo é refeito no servidor, com os mesmos parâmetros que a tela
        // usou: um relatório vindo do navegador seria um anexo que o cliente
        // escreve. Se o MultiVendas não responder na hora de gravar, a comissão
        // ainda sai — com o valor informado e sem anexo, o que o documento diz.
        $report = null;

        if (!empty($data['login']) && !empty($data['from']) && !empty($data['to'])) {
            try {
                $report = $reports->forSeller(
                    $data['login'],
                    Carbon::parse($data['from']),
                    Carbon::parse($data['to']),
                );
            } catch (SalesReportException $e) {
                report($e);
            }
        }

        // Alterou o valor que o relatório apurou? Então diga por quê. A conta é
        // feita aqui, com o relatório que o SERVIDOR acabou de refazer: a tela já
        // pede a justificativa no momento da edição, mas quem confere se o número
        // realmente difere do apurado não pode ser o navegador.
        //
        // 422 com o campo em `errors`, e não o 409 do serviço, porque isto é um
        // campo a preencher na tela aberta — não um contrato que virou de estado.
        $reason = $data['sales_adjustment_reason'] ?? null;

        if (FreelancerService::salesAdjustmentIsRequired($report, (float) $data['sales_amount'])
            && mb_strlen(trim((string) $reason)) < FreelancerService::SALES_ADJUSTMENT_REASON_MIN) {
            return response()->json([
                'error' => 'Explique a alteração do valor apurado — a justificativa entra no termo assinado.',
                'requires_adjustment_reason' => true,
                'report_base' => (float) ($report['base'] ?? 0),
                'errors' => [
                    'sales_adjustment_reason' => ['Explique a alteração do valor apurado — a justificativa entra no termo assinado.'],
                ],
            ], 422);
        }

        try {
            $commission = $this->freelancerService->createSalesCommission(
                $freelancerService,
                $data['method'],
                (float) $data['sales_amount'],
                $operator,
                $report,
                $reason,
            );
        } catch (FreelancerServiceLockedException $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        }

        $this->bumpCount();

        return response()->json([
            'service' => $this->servicePayload(
                $commission->load(['functionFreelancer', 'freelancer', 'baseService.functionFreelancer'])
            ),
            'session' => $this->sessionPayload(),
        ], 201);
    }

    /**
     * Dispara o código de liberação para TODOS os coordenadores do Comercial —
     * caminho para quando nenhum deles pode vir até o tablet digitar o PIN. Não
     * se escolhe destinatário: quem opera não precisa saber quem está de
     * plantão, e o mesmo número serve para qualquer um deles.
     *
     * A tela recebe só os e-mails mascarados e o horário de validade; o código
     * em si não volta na resposta, senão o operador o teria sem falar com
     * ninguém.
     */
    public function weeklyLimitCode(Request $request, WeeklyLimitCodeService $codes)
    {
        $operator = $this->operatorModeOrFail();

        $request->validate([
            'freelancer_id' => ['required', 'integer', 'exists:freelancers,id'],
            'start_date' => ['required', 'date'],
        ]);

        try {
            $code = $codes->issue(
                (int) $request->input('freelancer_id'),
                Carbon::parse($request->input('start_date'))->toDateString(),
                $operator,
            );
        } catch (CoordinatorAuthorizationException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'error' => 'Não foi possível enviar o e-mail com o código. Tente novamente ou use o PIN.',
            ], 502);
        }

        return response()->json([
            'sent_to' => WeeklyLimitCodeService::maskEmails($code->sent_to),
            'expires_at' => WeeklyLimitCodeService::formatExpiry($code->expires_at),
        ]);
    }

    /**
     * O documento que o tablet exibe — montado AQUI, pelo mesmo Blade que o
     * painel imprime, e não em JavaScript no tablet.
     *
     * O texto do instrumento é revisado pelo jurídico de tempos em tempos.
     * Mantê-lo em dois lugares obrigaria a escrever cada revisão duas vezes, e
     * no dia em que as duas divergissem o freelancer assinaria no tablet um
     * texto diferente do que o painel imprime — sendo o do tablet o que ele de
     * fato leu e assinou.
     *
     * `role` diz qual dos dois campos recebe o canvas da assinatura.
     */
    public function document(Request $request, FreelancerService $freelancerService)
    {
        // A mesma autorização da imagem da assinatura: qualquer operador do
        // tablet pode ver o documento. Quem pode ASSINÁ-LO é decidido no
        // endpoint da assinatura, que é onde o ato acontece.
        $operator = $this->operatorOrFail();

        $role = $request->query('role') === 'coordinator' ? 'coordinator' : 'freelancer';

        $freelancerService->load([
            'freelancer',
            'functionFreelancer',
            // O documento do aditivo cita o contrato que ele altera.
            'baseService.functionFreelancer',
            'freelancerSignedBy',
            'coordinatorSignedBy',
        ]);

        return response()->json([
            'html' => view('freelancer.services.partials.contract-document', [
                'service' => $freelancerService,
                'layout' => 'tablet',
                'signing' => $role,
                'operatorName' => $operator->name,
            ])->render(),
            // A redação exibida volta com o documento e é reenviada na
            // assinatura — ver a trava em signService().
            'contract_version' => $freelancerService->contractVersion(),
        ]);
    }

    /**
     * Assinatura do freelancer: exige o PIN do operador (reconfirmado a cada
     * assinatura), a chave PIX que o freelancer acabou de conferir e a imagem
     * do traço desenhado sobre o documento. É definitiva.
     */
    public function signService(Request $request, FreelancerService $freelancerService)
    {
        $operator = $this->operatorModeOrFail();

        $request->validate([
            'pin' => ['required', 'digits:6'],
            'signature' => ['required', 'string'],
            // A chave que estava na tela de conferência e no documento. Ver a
            // comparação abaixo.
            'pix_key' => ['required', 'string'],
            // A redação que o tablet exibiu. Opcional de propósito: uma tela
            // aberta desde antes do deploy não a envia, e recusar a assinatura
            // por isso seria pior do que a corrida que ela protege.
            'contract_version' => ['nullable', 'integer'],
        ]);

        if (!$operator->checkPin($request->input('pin'))) {
            return response()->json(['error' => 'PIN inválido.'], 401);
        }

        // Contrato de cadastro incompleto não pode ser assinado (cobre contratos
        // legados, gerados antes desta trava).
        if ($freelancerService->freelancer && !$freelancerService->freelancer->hasCompleteContractData()) {
            return response()->json([
                'error' => 'Cadastro do freelancer incompleto. Complete os dados antes de assinar o contrato.',
            ], 409);
        }

        // A chave mudou entre a conferência e a assinatura (alteração no painel,
        // outra sessão no tablet, tela esquecida aberta). O documento à frente
        // do freelancer cita a chave antiga: refaz-se a conferência.
        $chaveAtual = $freelancerService->freelancer?->pixKey();

        if ($chaveAtual !== null && $request->input('pix_key') !== $chaveAtual) {
            return response()->json([
                'error' => 'A chave PIX do freelancer mudou desde a conferência. Confira novamente antes de assinar.',
                'pix_key_changed' => true,
                'freelancer' => $freelancerService->freelancer
                    ? $this->freelancerPayload($freelancerService->freelancer)
                    : null,
            ], 409);
        }

        // Mesma ideia, para o TEXTO: o jurídico publicou uma redação nova
        // enquanto o documento estava aberto na tela. O que o freelancer leu não
        // é mais o que ele assinaria — recarrega-se antes de gravar.
        //
        // Só cabe aqui: o coordenador sempre assina um contrato que o freelancer
        // já assinou, e portanto de redação já congelada, que não muda mais.
        $versaoExibida = $request->input('contract_version');

        if ($versaoExibida !== null && (int) $versaoExibida !== $freelancerService->contractVersion()) {
            return response()->json([
                'error' => 'O texto do contrato foi atualizado desde que este documento foi aberto. Confira o novo texto com o freelancer antes de assinar.',
                'contract_version_changed' => true,
            ], 409);
        }

        try {
            $path = $this->storeSignatureImage($freelancerService, $request->input('signature'), 'freelancer');
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Não foi possível salvar a assinatura. Tente novamente.'], 422);
        }

        // O caminho entra no model ANTES de assinar: o save() de signAsFreelancer
        // grava data, autor e imagem de uma vez. Em dois saves, uma falha no
        // segundo deixaria o contrato assinado sem o traço — irrecuperável,
        // porque assinatura não se repete.
        $freelancerService->forceFill(['freelancer_signature_path' => $path]);

        try {
            // A chave conferida pelo freelancer é copiada para o contrato pelo
            // próprio signAsFreelancer, junto com a data e o autor.
            $this->freelancerService->signAsFreelancer($freelancerService, $operator, pixKeyConfirmed: true);
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($path);

            if ($e instanceof FreelancerServiceLockedException) {
                return response()->json(['error' => $e->getMessage()], 409);
            }

            throw $e;
        }

        return response()->json([
            'service' => $this->servicePayload($freelancerService->fresh()->load(['functionFreelancer', 'freelancer'])),
            'session' => $this->sessionPayload(),
        ]);
    }

    /**
     * Resposta do jantar, perguntada na tela seguinte à da assinatura.
     *
     * Não pede o PIN de novo, de propósito: quem responde é o freelancer, sobre
     * a própria refeição, e o PIN do operador acabou de ser conferido na
     * assinatura. Exigi-lo aqui só faria o operador digitar seis dígitos para
     * registrar um "sim" — e transformaria em obstáculo justamente a pergunta
     * que precisa ser rápida.
     *
     * Quem decide se a pergunta cabe é o servidor (`needsDinnerAnswer`), não a
     * tela: o tablet só exibe o que o payload da assinatura mandou perguntar, e
     * a regra é reconferida aqui antes de gravar.
     */
    public function dinnerAnswer(Request $request, FreelancerService $freelancerService)
    {
        $operator = $this->operatorModeOrFail();

        $request->validate([
            'wants_dinner' => ['required', 'boolean'],
        ]);

        try {
            $this->freelancerService->recordDinnerAnswer(
                $freelancerService,
                $request->boolean('wants_dinner'),
                $operator,
            );
        } catch (FreelancerServiceLockedException $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        }

        return response()->json([
            'service' => $this->servicePayload($freelancerService->fresh()->load(['functionFreelancer', 'freelancer'])),
            'session' => $this->sessionPayload(),
        ]);
    }

    /* ---------------------------------------------------------------------
     | Coordenador
     |---------------------------------------------------------------------*/

    /**
     * Fila do coordenador: contratos que o freelancer já assinou e que esperam
     * a contraparte. Os mais antigos primeiro — são os que travam o pagamento.
     * A lista é limitada: a tela do tablet não comporta uma fila inteira, e o
     * coordenador vai zerando do topo (a cada assinatura, a lista recarrega).
     */
    public function coordinatorServices()
    {
        $this->coordinatorModeOrFail();

        $services = FreelancerService::awaitingCoordinator()
            // `baseService` porque o coordenador também assina aditivos, e o
            // documento do aditivo cita o contrato que ele altera.
            ->with(['functionFreelancer', 'freelancer', 'batch', 'baseService.functionFreelancer', 'amendments'])
            ->orderBy('freelancer_signed_at')
            ->limit(self::COORDINATOR_QUEUE_LIMIT)
            ->get()
            ->map(fn(FreelancerService $s) => $this->coordinatorServicePayload($s));

        return response()->json($services);
    }

    /** Imagem da assinatura, protegida pela sessão do kiosk. */
    public function signatureImage(FreelancerService $freelancerService, string $party)
    {
        $this->operatorOrFail();

        return $this->signatureImageResponse($freelancerService, $party);
    }

    /**
     * Assinatura do coordenador pelo tablet: mesmo rito da assinatura do
     * freelancer — PIN reconfirmado + traço desenhado sobre o documento. É
     * definitiva e libera o contrato para o financeiro.
     */
    public function signServiceAsCoordinator(Request $request, FreelancerService $freelancerService)
    {
        $coordinator = $this->coordinatorModeOrFail();

        $request->validate([
            'pin' => ['required', 'digits:6'],
            'signature' => ['required', 'string'],
        ]);

        if (!$coordinator->checkPin($request->input('pin'))) {
            return response()->json(['error' => 'PIN inválido.'], 401);
        }

        // A fila só mostra contratos já assinados pelo freelancer; recusamos os
        // demais aqui também, porque a tela pode ter ficado aberta.
        if ($freelancerService->freelancer_signed_at === null) {
            return response()->json(['error' => 'O freelancer ainda não assinou este contrato.'], 409);
        }

        // Turno do dia ainda em aberto: a fila não o mostra, mas a tela pode ter
        // ficado aberta desde ontem. Recusado ANTES de gravar o traço — não faz
        // sentido guardar a imagem de uma assinatura que não vai acontecer.
        if ($motivo = $freelancerService->releaseBlockReason()) {
            return response()->json(['error' => $motivo], 409);
        }

        // Mesma trava defensiva da assinatura do freelancer: cadastro incompleto
        // não gera contrato assinado.
        if ($freelancerService->freelancer && !$freelancerService->freelancer->hasCompleteContractData()) {
            return response()->json([
                'error' => 'Cadastro do freelancer incompleto. Complete os dados antes de assinar o contrato.',
            ], 409);
        }

        try {
            $path = $this->storeSignatureImage($freelancerService, $request->input('signature'), 'coordinator');
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Não foi possível salvar a assinatura. Tente novamente.'], 422);
        }

        // Mesma razão da assinatura do freelancer: data, autor e imagem num save só.
        $freelancerService->forceFill(['coordinator_signature_path' => $path]);

        try {
            $this->freelancerService->signAsCoordinator($freelancerService, $coordinator);
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($path);

            if ($e instanceof FreelancerServiceLockedException) {
                return response()->json(['error' => $e->getMessage()], 409);
            }

            throw $e;
        }

        return response()->json([
            'service' => $this->coordinatorServicePayload(
                $freelancerService->fresh()->load(['functionFreelancer', 'freelancer'])
            ),
            'session' => $this->sessionPayload(),
        ]);
    }

    /* ---------------------------------------------------------------------
     | Lote de aprovação (montado pelo coordenador, no tablet)
     |---------------------------------------------------------------------*/

    /** Rascunho atual + contratos que ainda podem entrar nele. */
    public function batch()
    {
        $coordinator = $this->coordinatorModeOrFail();

        $draft = $this->batches->existingDraftFor($coordinator);
        $draft?->load(['services.functionFreelancer', 'services.freelancer']);

        return response()->json([
            'draft' => $draft ? $this->batchPayload($draft) : null,
            'available' => FreelancerService::availableForBatch()
                ->with(['functionFreelancer', 'freelancer'])
                ->orderByDesc('start_date')
                ->limit(self::COORDINATOR_QUEUE_LIMIT)
                ->get()
                ->map(fn(FreelancerService $s) => $this->batchItemPayload($s)),
        ]);
    }

    /** Inclui contratos no rascunho, criando-o na primeira inclusão. */
    public function addToBatch(Request $request)
    {
        $coordinator = $this->coordinatorModeOrFail();

        $request->validate([
            'services' => ['required', 'array'],
            'services.*' => ['integer'],
        ]);

        $draft = $this->batches->draftFor($coordinator);

        try {
            $added = $this->batches->addServices($draft, array_map('intval', $request->input('services')));
        } catch (FreelancerBatchException $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        }

        return response()->json([
            'added' => $added,
            'draft' => $this->batchPayload($draft->fresh()->load(['services.functionFreelancer', 'services.freelancer'])),
        ]);
    }

    public function removeFromBatch(FreelancerService $freelancerService)
    {
        $coordinator = $this->coordinatorModeOrFail();

        $draft = $this->batches->existingDraftFor($coordinator);

        if (!$draft) {
            return response()->json(['error' => 'Não há lote em rascunho.'], 409);
        }

        try {
            $this->batches->removeService($draft, $freelancerService);
        } catch (FreelancerBatchException $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        }

        return response()->json([
            'draft' => $this->batchPayload($draft->fresh()->load(['services.functionFreelancer', 'services.freelancer'])),
        ]);
    }

    /** Envia o lote para a gerência. A aprovação, por processo, só acontece na web. */
    public function sendBatch(Request $request)
    {
        $coordinator = $this->coordinatorModeOrFail();

        $request->validate(['pin' => ['required', 'digits:6']]);

        if (!$coordinator->checkPin($request->input('pin'))) {
            return response()->json(['error' => 'PIN inválido.'], 401);
        }

        $draft = $this->batches->existingDraftFor($coordinator);

        if (!$draft) {
            return response()->json(['error' => 'Não há lote em rascunho para enviar.'], 409);
        }

        try {
            $this->batches->send($draft, $coordinator);
        } catch (FreelancerBatchException $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        }

        return response()->json([
            'batch' => $this->batchPayload($draft->fresh()->load(['services.functionFreelancer', 'services.freelancer'])),
            'session' => $this->sessionPayload(),
        ]);
    }

    private function batchPayload(FreelancerServiceBatch $batch): array
    {
        return [
            'id' => $batch->id,
            'status' => $batch->status,
            'status_label' => $batch->statusLabel(),
            'count' => $batch->services->count(),
            'total' => (float) $batch->services->sum('price'),
            'services' => $batch->services->map(fn(FreelancerService $s) => $this->batchItemPayload($s))->values(),
        ];
    }

    /** Linha enxuta: a tela do tablet lista, não detalha. */
    private function batchItemPayload(FreelancerService $s): array
    {
        return [
            'id' => $s->id,
            'freelancer' => $s->freelancer?->name,
            'function' => $s->functionFreelancer?->name,
            'is_amendment' => $s->isAmendment(),
            'location' => $s->location,
            'start_date_br' => $s->start_date ? Carbon::parse($s->start_date)->format('d/m/Y') : null,
            'start_time' => substr((string) $s->start_time, 0, 5),
            'end_time' => substr((string) $s->end_time, 0, 5),
            'price' => (float) $s->price,
            'rejection_reason' => $s->isManagerRejected() ? $s->manager_rejection_reason : null,
        ];
    }

    /* ---------------------------------------------------------------------
     | Sessão do operador
     |---------------------------------------------------------------------*/

    private function currentOperator(): ?User
    {
        $id = session('kiosk.operator_id');

        return $id ? User::find($id) : null;
    }

    private function sessionActive(): bool
    {
        if (!session('kiosk.operator_id')) {
            return false;
        }

        $startedAt = session('kiosk.started_at');

        if (!$startedAt || now()->timestamp - $startedAt > self::SESSION_MINUTES * 60) {
            return false;
        }

        // O teto de contratos vale para o atendimento a freelancers; o
        // coordenador percorre a fila inteira dentro da janela de tempo.
        if (session('kiosk.mode') === self::MODE_COORDINATOR) {
            return true;
        }

        return session('kiosk.count', 0) < self::SESSION_MAX_CONTRACTS;
    }

    /** Garante uma sessão de kiosk válida e devolve quem está logado; senão, 419. */
    private function operatorOrFail(): User
    {
        if ($this->sessionActive()) {
            $operator = $this->currentOperator();

            if ($operator) {
                return $operator;
            }
        }

        $reason = session('kiosk.count', 0) >= self::SESSION_MAX_CONTRACTS
            ? 'Sessão encerrada: limite de ' . self::SESSION_MAX_CONTRACTS . ' contratos. Entre novamente.'
            : 'Sessão expirada (' . self::SESSION_MINUTES . ' min). Entre novamente.';

        $this->clearSession();

        throw new HttpResponseException(
            response()->json(['error' => $reason, 'expired' => true], 419)
        );
    }

    /**
     * Modos que este usuário pode assumir no kiosk, na ordem em que aparecem na
     * tela de escolha. Vazio = sem acesso.
     *
     * @return array<int, string>
     */
    private function availableModes(User $user): array
    {
        return array_values(array_filter([
            $user->can('manage freelancers') ? self::MODE_OPERATOR : null,
            $user->isCoordinatorOfSectorNamed(self::COORDINATOR_SECTOR) ? self::MODE_COORDINATOR : null,
        ]));
    }

    /**
     * Sessão válida + modo em uso. O papel é reconferido a cada requisição: se
     * a permissão for retirada no painel, a sessão aberta no tablet para de
     * valer na hora.
     */
    private function inModeOrFail(string $mode, string $denial): User
    {
        $operator = $this->operatorOrFail();

        if (session('kiosk.mode') !== $mode || !in_array($mode, $this->availableModes($operator), true)) {
            throw new HttpResponseException(response()->json(['error' => $denial], 403));
        }

        return $operator;
    }

    private function operatorModeOrFail(): User
    {
        return $this->inModeOrFail(self::MODE_OPERATOR, 'Este usuário não atende freelancers no kiosk.');
    }

    private function coordinatorModeOrFail(): User
    {
        return $this->inModeOrFail(
            self::MODE_COORDINATOR,
            'Apenas o coordenador do setor ' . self::COORDINATOR_SECTOR . ' assina contratos no kiosk.'
        );
    }

    private function bumpCount(): void
    {
        session(['kiosk.count' => session('kiosk.count', 0) + 1]);
    }

    private function clearSession(): void
    {
        session()->forget(['kiosk.operator_id', 'kiosk.started_at', 'kiosk.count', 'kiosk.mode']);
    }

    private function sessionPayload(): array
    {
        $startedAt = session('kiosk.started_at', now()->timestamp);
        $elapsed = now()->timestamp - $startedAt;

        return [
            'remaining_seconds' => max(0, self::SESSION_MINUTES * 60 - $elapsed),
            'count' => session('kiosk.count', 0),
            'max' => self::SESSION_MAX_CONTRACTS,
            'mode' => session('kiosk.mode'),
            // O coordenador não tem teto de contratos: a barra do topo esconde
            // o contador em vez de mostrar um limite que não existe.
            'counts_contracts' => session('kiosk.mode') !== self::MODE_COORDINATOR,
        ];
    }

    /* ---------------------------------------------------------------------
     | Payloads
     |---------------------------------------------------------------------*/

    private function operatorPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'matricula' => $user->matricula,
            'modes' => $this->availableModes($user),
            'coordinator_sector' => self::COORDINATOR_SECTOR,
        ];
    }

    private function freelancerPayload(Freelancer $f): array
    {
        return [
            'id' => $f->id,
            'name' => $f->name,
            'cpf' => $f->cpf,
            // A chave crua vai para a comparação da assinatura; a formatada e o
            // tipo, para a tela de conferência e para o texto do documento.
            'pix_key' => $f->pixKey(),
            'pix_key_formatted' => $f->pixKeyFormatted(),
            'pix_key_type' => $f->pixKeyType(),
            'pix_key_type_label' => $f->pixKeyTypeLabel(),
            'pix_key_is_cpf' => $f->pixKeyType() === Freelancer::PIX_KEY_CPF,
            'rg' => $f->rg,
            'nacionality' => $f->nacionality,
            'civil_status' => $f->civil_status,
            'address' => $f->address,
            'telephone' => $f->telephone,
            'email' => $f->email,
            // A tela usa isto para bloquear o registro do contrato e abrir o
            // formulário de completar os dados faltantes.
            'complete' => $f->hasCompleteContractData(),
            'missing_fields' => $f->missingContractFields(),
            'missing_field_labels' => $f->missingContractFieldLabels(),
        ];
    }

    /**
     * O contrato como as TELAS do tablet precisam dele — listagem, prévia do
     * aditivo, prévia da comissão.
     *
     * O que alimentava o documento (texto das cláusulas, qualificação das
     * partes, dados do contrato aditado, chave PIX citada, relatório do Anexo I)
     * saiu daqui: o documento é montado no servidor e chega pronto por
     * `document()`. Repetir esses campos aqui só voltaria a permitir que alguém
     * montasse um segundo documento no cliente.
     */
    private function servicePayload(FreelancerService $s): array
    {
        return [
            'id' => $s->id,
            'function' => $s->functionFreelancer?->name,
            // O que a tela pode oferecer neste contrato: assinar e/ou aditivar.
            // O motivo de uma recusa não vem aqui — quem não pode nem uma coisa
            // nem outra sequer entra na lista; a explicação chega no 409.
            'can_be_signed' => $s->canBeSignedByFreelancer(),
            'can_be_amended' => $s->canBeAmended(),
            'is_amendment' => $s->isAmendment(),
            'is_commission' => $s->isCommissionAmendment(),
            'can_receive_commission' => $s->canReceiveCommission() && $this->withinCommissionWindow($s),
            'commission_block_reason' => $s->commissionBlockReason(),
            // Recebeu aditivo: continua sendo assinado, mas quem paga é o outro.
            'is_amended' => $s->isAmended(),
            'function_id' => $s->function_freelancer_id,
            'location' => $s->location,
            'start_date' => $s->start_date?->toDateString(),
            'start_date_br' => $s->start_date ? Carbon::parse($s->start_date)->format('d/m/Y') : null,
            'start_time' => substr((string) $s->start_time, 0, 5),
            'end_date_br' => $s->end_date ? Carbon::parse($s->end_date)->format('d/m/Y') : null,
            'end_time' => substr((string) $s->end_time, 0, 5),
            'crosses_midnight' => ($s->start_date && $s->end_date) ? $s->start_date->ne($s->end_date) : false,
            'total_hours' => $s->total_hours,
            'price' => (float) $s->price,
            // Valor do bloco de 15 min da função: é com ele que a prévia do
            // aditivo recalcula o preço na tela, sem inventar uma segunda regra.
            'block_price' => (float) ($s->functionFreelancer?->price ?? 0),
            'duration_minutes' => $s->durationInMinutes(),
            'status_label' => $s->signatureLabel(),
            // Jantar do turno noturno: é `needs_dinner_answer` que faz a tela
            // abrir a pergunta depois da assinatura. Quem decide é o servidor.
            'needs_dinner_answer' => $s->needsDinnerAnswer(),
            'dinner_wanted' => $s->dinner_wanted,
            'dinner_window' => FreelancerService::dinnerWindowLabel(),
        ];
    }

    /**
     * Contrato na fila do coordenador. Traz os dados do freelancer, que a fila
     * exibe; o traço da outra parte já vem desenhado dentro do documento que o
     * servidor monta.
     */
    private function coordinatorServicePayload(FreelancerService $s): array
    {
        return $this->servicePayload($s) + [
            'freelancer' => $s->freelancer ? $this->freelancerPayload($s->freelancer) : null,
            'freelancer_signed_at_br' => $s->freelancer_signed_at?->format('d/m/Y H:i'),
        ];
    }

    private function weeklyLimitPayload(array $data): array
    {
        $count = FreelancerService::countInWeeklyWindow($data['freelancer_id'], $data['start_date']);
        $freelancer = Freelancer::find($data['freelancer_id']);

        return [
            'error' => 'Limite semanal excedido',
            'requires_confirmation' => true,
            // Não é o PIN do operador: só o coordenador do Comercial libera, e
            // ele se identifica pela própria matrícula.
            'requires_coordinator_pin' => true,
            'coordinator_sector' => self::COORDINATOR_SECTOR,
            'weekly_limit' => FreelancerService::WEEKLY_LIMIT,
            'services_in_window' => $count,
            'services_after_save' => $count + 1,
            'message' => 'Com este registro, ' . ($freelancer?->name ?? 'o freelancer') . ' passa a ter '
                . ($count + 1) . ' serviços numa janela de ' . FreelancerService::WEEKLY_WINDOW_DAYS
                . ' dias (limite: ' . FreelancerService::WEEKLY_LIMIT . ').',
        ];
    }

    /**
     * Decodifica a data URL (image/png) do canvas e grava no disco público.
     * Retorna o caminho relativo salvo. $party ("freelancer" ou "coordinator")
     * entra no nome do arquivo para separar os dois traços do mesmo contrato.
     */
    private function storeSignatureImage(FreelancerService $service, string $dataUrl, string $party): string
    {
        if (!preg_match('/^data:image\/(png|jpeg);base64,/', $dataUrl)) {
            throw new \InvalidArgumentException('Formato de assinatura inválido.');
        }

        $binary = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1), true);

        if ($binary === false) {
            throw new \InvalidArgumentException('Assinatura inválida.');
        }

        $path = 'signatures/service_' . $service->id . '_' . $party . '_' . now()->format('YmdHis') . '.png';
        Storage::disk('public')->put($path, $binary);

        return $path;
    }
}
