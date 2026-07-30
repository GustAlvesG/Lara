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

        $query = CompanyAccessLog::with('company', 'worker', 'appDriver', 'uberRequest')->latest();

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

        if ($request->filled('status') && array_key_exists($request->status, UberAccessRequest::STATUS_LABELS)) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $requests = $query->paginate(25)->withQueryString();
        $statuses = UberAccessRequest::STATUS_LABELS;

        $stats = [
            'total'      => UberAccessRequest::whereDate('created_at', today())->count(),
            'aguardando' => UberAccessRequest::where('status', UberAccessRequest::STATUS_AGUARDANDO_ACESSO)->count(),
            'concluido'  => UberAccessRequest::whereDate('created_at', today())->where('status', UberAccessRequest::STATUS_CONCLUIDO)->count(),
            'expirado'   => UberAccessRequest::whereDate('created_at', today())->where('status', UberAccessRequest::STATUS_EXPIRADO)->count(),
        ];

        return view('companies.uber.requests', compact('requests', 'statuses', 'stats'));
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
}
