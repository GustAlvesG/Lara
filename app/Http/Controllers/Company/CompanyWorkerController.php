<?php

namespace App\Http\Controllers\Company;

use App\Models\Company\Company;
use App\Models\Company\CompanyWorker;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCompanyWorkerRequest;
use App\Http\Requests\UpdateCompanyWorkerRequest;
use App\Services\CompanyService;
use Illuminate\Http\Request;

class CompanyWorkerController extends Controller
{
    protected $companyService;

    public function __construct(CompanyService $companyService)
    {
        $this->companyService = $companyService;
    }

    public function search(Request $request)
    {
        $q = trim($request->get('q', ''));
        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $normalized = preg_replace('/\D/', '', $q);

        $workers = CompanyWorker::with('company')
            ->where(function ($query) use ($q, $normalized) {
                // Funcionário por nome
                $query->where('name', 'like', "%{$q}%");
                // Funcionário por CPF (somente dígitos)
                if ($normalized) {
                    $query->orWhere('document', 'like', "%{$normalized}%");
                }
                // Funcionários de empresas cujo nome casa com a busca
                $query->orWhereHas('company', function ($c) use ($q) {
                    $c->where('name', 'like', "%{$q}%");
                });
            })
            ->orderBy('name')
            ->limit(50)
            ->get()
            ->map(function ($w) {
                $doc = $w->document;
                $formatted = $doc
                    ? preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $doc)
                    : null;
                return [
                    'id'           => $w->id,
                    'name'         => $w->name,
                    'position'     => $w->position,
                    'document'     => $formatted,
                    'telephone'    => $w->telephone,
                    'image'        => $w->image ? asset('images/' . $w->image) : null,
                    'company_id'   => $w->company_id,
                    'company_name' => $w->company?->name,
                    'worker_url'   => route('company.worker.show', [$w->company_id, $w->id]),
                    'company_url'  => route('company.show', $w->company_id),
                ];
            });

        return response()->json($workers);
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
