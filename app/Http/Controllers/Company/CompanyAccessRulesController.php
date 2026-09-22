<?php

namespace App\Http\Controllers\Company;

use App\Models\Company\Company;
use App\Models\Company\CompanyWorker;
use App\Models\Company\CompanyAccessRule;
use App\Models\Company\CompanyAccessLog;
use App\Models\UberAccessRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCompanyAccessRulesRequest;
use App\Http\Requests\UpdateCompanyAccessRulesRequest;
use App\Services\CompanyService;
use Illuminate\Http\Request;

class CompanyAccessRulesController extends Controller
{
    public function __construct(private CompanyService $companyService)
    {
        $this->companyService = $companyService;
    }

    public function create(Company $company)
    {
        return view('companies.rules.create', ['company' => $company->id]);
    }

    public function createForWorker(Company $company, CompanyWorker $worker)
    {
        return view('companies.rules.create', ['company' => $company->id, 'worker' => $worker]);
    }

    public function store(StoreCompanyAccessRulesRequest $request)
    {
        try {
            $this->companyService->storeAccessRule($request->all());

            if ($request->company_worker_id) {
                return redirect()->route('company.worker.show', [$request->company_id, $request->company_worker_id])
                    ->with('success', 'Regra de acesso criada com sucesso.');
            }

            return redirect()->route('company.show', $request->company_id)
                ->with('success', 'Regra de acesso criada com sucesso.');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Ocorreu um erro ao criar a regra de acesso: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function edit(Company $company, CompanyAccessRule $rule)
    {
        $rule->load('weekdays', 'creator', 'editor');
        return view('companies.rules.edit', compact('company', 'rule'));
    }

    public function update(UpdateCompanyAccessRulesRequest $request, Company $company, CompanyAccessRule $rule)
    {
        try {
            $this->companyService->updateAccessRule($request->all(), $rule);

            if ($rule->company_worker_id) {
                return redirect()->route('company.worker.show', [$company->id, $rule->company_worker_id])
                    ->with('success', 'Regra de acesso atualizada com sucesso.');
            }

            return redirect()->route('company.show', $company->id)
                ->with('success', 'Regra de acesso atualizada com sucesso.');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Ocorreu um erro ao atualizar a regra: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function destroy(Company $company, CompanyAccessRule $rule)
    {
        $rule->delete();
        return redirect()->route('company.show', $company->id)
            ->with('success', 'Regra de acesso removida com sucesso.');
    }

    public function bulkDestroy(Request $request, Company $company)
    {
        $ids = array_filter((array) $request->input('rule_ids', []), 'is_numeric');
        $deleted = CompanyAccessRule::where('company_id', $company->id)->whereIn('id', $ids)->delete();
        return redirect()->route('company.show', $company->id)
            ->with('success', $deleted . ' regra(s) removida(s) com sucesso.');
    }

    public function monitor()
    {
        return view('companies.access-monitor');
    }

    public function accessLogs(Request $request)
    {
        $type = $request->input('type') === 'app' ? 'app' : 'all';

        $query = CompanyAccessLog::with('company', 'worker', 'appDriver', 'uberRequest', 'freelancer', 'freelancerService', 'oneOffAccess.creator')->latest();

        // Aba separada: apenas acessos de carros de aplicativo (Uber e motoristas de app).
        if ($type === 'app') {
            $query->appCars();
        }

        // Empresa não se aplica a carros de aplicativo (só filtro por data).
        if ($type !== 'app' && $request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        if ($request->filled('status') && in_array($request->status, ['1', '0'])) {
            $query->where('allowed', (bool) $request->status);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $query->paginate(25)->withQueryString();
        $companies = Company::orderBy('name')->get();

        // Estatísticas do dia respeitam a aba selecionada.
        $statsBase = fn () => CompanyAccessLog::whereDate('created_at', today())
            ->when($type === 'app', fn ($q) => $q->appCars());

        $stats = [
            'total'   => $statsBase()->count(),
            'allowed' => $statsBase()->where('allowed', true)->count(),
            'denied'  => $statsBase()->where('allowed', false)->count(),
        ];

        return view('companies.access-logs', compact('logs', 'companies', 'stats', 'type'));
    }

    /**
     * Lista TODOS os pedidos de Uber (uber_access_requests), em qualquer status
     * — não só os que viraram acesso. Aba "Pedidos" da seção de Uber.
     */
    public function uberRequests(Request $request)
    {
        $query = UberAccessRequest::query()->latest('id');

        $this->applyUberRequestFilters($query, $request);

        $requests = $query->paginate(25)->withQueryString();
        $statuses = UberAccessRequest::STATUS_LABELS;
        $memberValidations = UberAccessRequest::MEMBER_VALIDATION_LABELS;

        $stats = [
            'total'      => UberAccessRequest::whereDate('created_at', today())->count(),
            'aguardando' => UberAccessRequest::where('status', UberAccessRequest::STATUS_AGUARDANDO_ACESSO)->count(),
            'concluido'  => UberAccessRequest::whereDate('created_at', today())->where('status', UberAccessRequest::STATUS_CONCLUIDO)->count(),
            'expirado'   => UberAccessRequest::whereDate('created_at', today())->where('status', UberAccessRequest::STATUS_EXPIRADO)->count(),
        ];

        return view('companies.uber.requests', compact('requests', 'statuses', 'memberValidations', 'stats'));
    }

    /**
     * Filtros da aba "Pedidos". Todos combinam entre si (E), e todos os campos
     * de texto são "contém" — o dado vem digitado no WhatsApp e raramente
     * chega exatamente como quem procura espera.
     *
     * Placa e telefone são comparados sem máscara: o banco guarda a placa
     * normalizada (ABC1D23) e o telefone como a Poli entrega, então procurar
     * por "ABC-1D23" ou "(41) 99999-9999" precisa achar mesmo assim.
     */
    private function applyUberRequestFilters($query, Request $request): void
    {
        if ($request->filled('status') && array_key_exists($request->status, UberAccessRequest::STATUS_LABELS)) {
            $query->where('status', $request->status);
        }

        if ($request->filled('member_validation')
            && array_key_exists($request->member_validation, UberAccessRequest::MEMBER_VALIDATION_LABELS)) {
            $query->where('member_validation', $request->member_validation);
        }

        if ($request->filled('name')) {
            $name = trim($request->input('name'));
            $query->where(function ($q) use ($name) {
                $q->where('requester_name', 'like', "%{$name}%")
                    ->orWhere('contact_name_whatsapp', 'like', "%{$name}%");
            });
        }

        if ($request->filled('plate')) {
            $plate = $this->onlyAlphanumeric($request->input('plate'));
            $query->where('vehicle_plate', 'like', "%{$plate}%");
        }

        if ($request->filled('matricula')) {
            $matricula = trim($request->input('matricula'));
            $digits = $this->onlyDigits($matricula);
            $query->where(function ($q) use ($matricula, $digits) {
                $q->where('matricula', 'like', "%{$matricula}%");
                if ($digits !== '') {
                    $q->orWhereRaw($this->unmasked('matricula') . ' like ?', ["%{$digits}%"]);
                }
            });
        }

        if ($request->filled('phone')) {
            $phone = $this->onlyDigits($request->input('phone'));
            if ($phone !== '') {
                $query->whereRaw($this->unmasked('contact_phone') . ' like ?', ["%{$phone}%"]);
            }
        }

        if ($request->filled('location')) {
            $location = trim($request->input('location'));
            $query->where('club_location', 'like', "%{$location}%");
        }

        // Busca livre: uma caixa só para quem não sabe em qual campo o dado
        // caiu — e no fluxo do WhatsApp isso acontece o tempo todo (o nome vem
        // no campo da matrícula, a placa vem no campo do local...).
        if ($request->filled('q')) {
            $term = trim($request->input('q'));
            $digits = $this->onlyDigits($term);
            $alnum = $this->onlyAlphanumeric($term);

            $query->where(function ($q) use ($term, $digits, $alnum) {
                $q->where('requester_name', 'like', "%{$term}%")
                    ->orWhere('contact_name_whatsapp', 'like', "%{$term}%")
                    ->orWhere('club_location', 'like', "%{$term}%")
                    ->orWhere('matricula', 'like', "%{$term}%")
                    ->orWhere('member_validation_name', 'like', "%{$term}%");

                if ($alnum !== '') {
                    $q->orWhere('vehicle_plate', 'like', "%{$alnum}%");
                }

                if ($digits !== '') {
                    $q->orWhereRaw($this->unmasked('contact_phone') . ' like ?', ["%{$digits}%"])
                        ->orWhereRaw($this->unmasked('matricula') . ' like ?', ["%{$digits}%"]);
                }
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
    }

    private function onlyDigits(?string $value): string
    {
        return preg_replace('/\D/', '', (string) $value);
    }

    private function onlyAlphanumeric(?string $value): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $value));
    }

    /**
     * A mesma coluna, sem os separadores de máscara, para comparar com um
     * termo já reduzido a dígitos.
     *
     * Sem isso a busca só acha quando a máscara do que está gravado bate com a
     * do que foi digitado — e aqui os dois lados são texto livre: a
     * matrícula/CPF vem do que o associado digitou no WhatsApp, e quem procura
     * digita como leu. Dispensa índice de propósito: a tabela é pequena e a
     * consulta já é toda em LIKE %...%.
     *
     * O nome da coluna nunca vem de entrada do usuário — é literal no
     * chamador, e assim precisa continuar.
     */
    private function unmasked(string $column): string
    {
        foreach (['.', '-', '/', ' ', '(', ')', '+'] as $separator) {
            $column = "replace({$column}, '{$separator}', '')";
        }

        return $column;
    }

    /**
     * Painel da portaria: os pedidos prontos, esperando o motorista chegar.
     *
     * Existe porque a consulta por placa do monitor é exata, e a placa é o
     * campo que mais chega errado do WhatsApp. Aqui o porteiro vê a fila
     * inteira, acha o pedido pelo nome/print/local, corrige a placa se for o
     * caso e libera — sem depender de o associado ter digitado certo.
     *
     * Sem paginação de propósito: a validade é de 30 minutos, a fila real é de
     * poucas linhas, e paginar uma tela de portaria esconderia justamente o
     * pedido que se está procurando. O teto é só uma trava de segurança.
     */
    public function uberWaiting(Request $request)
    {
        $waiting = UberAccessRequest::where('status', UberAccessRequest::STATUS_AGUARDANDO_ACESSO)
            ->orderByRaw('expires_at is null')
            ->orderBy('expires_at')
            ->limit(200)
            ->get();

        // Vencido ainda aparece: o cron que expira roda a cada minuto, e o
        // motorista que chegou 30s atrasado não pode ficar sem saída.
        [$expirados, $validos] = $waiting->partition(
            fn (UberAccessRequest $req) => $req->expires_at !== null && $req->expires_at->isPast()
        );

        // Quem ainda está respondendo o WhatsApp agora. É o outro lado do
        // mesmo problema: o motorista chega antes de o associado terminar de
        // preencher, e o porteiro precisa saber que o pedido existe.
        $emPreenchimento = UberAccessRequest::whereIn('status', UberAccessRequest::CAPTURE_STATUSES)
            ->where('last_message_at', '>=', now()->subMinutes(15))
            ->latest('last_message_at')
            ->limit(50)
            ->get();

        return view('companies.uber.waiting', [
            'validos'         => $validos->values(),
            'expirados'       => $expirados->values(),
            'emPreenchimento' => $emPreenchimento,
        ]);
    }

    /**
     * Lista os acessos efetivamente realizados a partir do fluxo de Uber
     * (company_access_logs). Aba "Acessos Realizados" da seção de Uber.
     */
    public function uberAccesses(Request $request)
    {
        $query = CompanyAccessLog::uber()->with('uberRequest')->latest();

        if ($request->filled('status') && in_array($request->status, ['1', '0'])) {
            $query->where('allowed', (bool) $request->status);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $query->paginate(25)->withQueryString();

        $statsBase = fn () => CompanyAccessLog::uber()->whereDate('created_at', today());

        $stats = [
            'total'   => $statsBase()->count(),
            'allowed' => $statsBase()->where('allowed', true)->count(),
            'denied'  => $statsBase()->where('allowed', false)->count(),
        ];

        return view('companies.uber.accesses', compact('logs', 'stats'));
    }

    public function validateCompanyAccess(Request $request)
    {
        $request->validate(['target' => 'required|string']);

        $result = $this->companyService->validateTryToAccess($request->all());

        $status = $result['found'] ? 200 : 404;

        return response()->json($result, $status);
    }

    public function registerAccess(Request $request)
    {
        $request->validate(['target' => 'required|string']);

        $result = $this->companyService->registerAccess($request->all());

        $status = $result['found'] ? 200 : 404;

        return response()->json($result, $status);
    }

    public function registerWorkerAccess(Request $request)
    {
        $request->validate(['worker_id' => 'required|integer|exists:company_workers,id']);

        $result = $this->companyService->registerWorkerAccess($request->integer('worker_id'));

        return response()->json($result, $result['found'] ? 200 : 404);
    }

    /**
     * Contraparte do registerWorkerAccess para o freelancer: o monitor já sabe
     * quem é (veio da consulta por CPF) e manda gravar o acesso daquela pessoa.
     */
    public function registerFreelancerAccess(Request $request)
    {
        $request->validate(['freelancer_id' => 'required|integer|exists:freelancers,id']);

        $result = $this->companyService->registerFreelancerAccess($request->integer('freelancer_id'));

        return response()->json($result, $result['found'] ? 200 : 404);
    }

    /** O mesmo, para a linha de liberação pontual: registrar queima a entrada. */
    public function registerOneOffAccess(Request $request)
    {
        $request->validate(['one_off_access_id' => 'required|integer|exists:one_off_accesses,id']);

        $result = $this->companyService->registerOneOffAccess($request->integer('one_off_access_id'));

        return response()->json($result, $result['found'] ? 200 : 404);
    }

    /**
     * Libera um pedido de Uber pelo id, a partir da tela "Aguardando acesso do
     * motorista". A placa não entra na conta: quem escolheu a linha foi o
     * porteiro, com o carro à vista.
     */
    public function registerUberRequestAccess(Request $request)
    {
        $request->validate([
            'uber_access_request_id' => 'required|integer|exists:uber_access_requests,id',
        ]);

        $result = $this->companyService->registerUberAccessById($request->integer('uber_access_request_id'));

        return response()->json($result, $result['found'] ? 200 : 409);
    }

    /** Correção da placa de um pedido que ainda aguarda o motorista. */
    public function updateUberRequestPlate(Request $request)
    {
        $request->validate([
            'uber_access_request_id' => 'required|integer|exists:uber_access_requests,id',
            'plate'                  => 'required|string|max:10',
        ]);

        $result = $this->companyService->updateUberRequestPlate(
            $request->integer('uber_access_request_id'),
            $request->input('plate')
        );

        return response()->json($result, $result['found'] ? 200 : 422);
    }
}
