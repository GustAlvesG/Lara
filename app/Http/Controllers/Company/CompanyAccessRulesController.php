<?php

namespace App\Http\Controllers\Company;

use App\Models\Company\Company;
use App\Models\Company\CompanyWorker;
use App\Models\Company\CompanyAccessRule;
use App\Models\Company\CompanyAccessLog;
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
        $rule->load('weekdays');
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

    public function monitor()
    {
        return view('companies.access-monitor');
    }

    public function accessLogs(Request $request)
    {
        $query = CompanyAccessLog::with('company', 'worker')->latest();

        if ($request->filled('company_id')) {
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

        $stats = [
            'total'   => CompanyAccessLog::whereDate('created_at', today())->count(),
            'allowed' => CompanyAccessLog::whereDate('created_at', today())->where('allowed', true)->count(),
            'denied'  => CompanyAccessLog::whereDate('created_at', today())->where('allowed', false)->count(),
        ];

        return view('companies.access-logs', compact('logs', 'companies', 'stats'));
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
