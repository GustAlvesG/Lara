<?php

namespace App\Http\Controllers\Company;

use App\Models\Company\Company;
use App\Models\Company\CompanyWorker;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCompanyWorkerRequest;
use App\Http\Requests\UpdateCompanyWorkerRequest;
use App\Services\CompanyService;

class CompanyWorkerController extends Controller
{
    protected $companyService;

    public function __construct(CompanyService $companyService)
    {
        $this->companyService = $companyService;
    }

    public function create(Company $company)
    {
        return view('companies.workers.create', ['companyId' => $company->id]);
    }

    public function store(StoreCompanyWorkerRequest $request)
    {
        try {
            $this->companyService->storeWorker($request->all());
            return redirect()->route('company.show', $request->company_id)
                             ->with('success', 'Funcionário adicionado com sucesso.');
        } catch (\Exception $e) {
            return redirect()->back()
                             ->withInput()
                             ->with('error', 'Ocorreu um erro ao adicionar o funcionário: ' . $e->getMessage());
        }
    }

    public function show(Company $company, CompanyWorker $worker)
    {
        $worker->load('rules.weekdays');
        return view('companies.workers.show', compact('company', 'worker'));
    }

    public function edit(Company $company, CompanyWorker $worker)
    {
        return view('companies.workers.edit', compact('company', 'worker'));
    }

    public function update(UpdateCompanyWorkerRequest $request, Company $company, CompanyWorker $worker)
    {
        try {
            $this->companyService->updateWorker($request->all(), $worker);
            return redirect()->route('company.worker.show', [$company->id, $worker->id])
                             ->with('success', 'Funcionário atualizado com sucesso.');
        } catch (\Exception $e) {
            return redirect()->back()
                             ->withInput()
                             ->with('error', 'Ocorreu um erro ao atualizar o funcionário: ' . $e->getMessage());
        }
    }

    public function destroy(Company $company, CompanyWorker $worker)
    {
        $worker->delete();
        return redirect()->route('company.show', $company->id)
                         ->with('success', 'Funcionário removido com sucesso.');
    }
}
