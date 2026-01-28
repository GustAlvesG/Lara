<?php

namespace App\Http\Controllers\Company;

use App\Models\CompanyAccessRules;
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
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create($company)
    {
        return view('companies.rules.create', compact('company'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCompanyAccessRulesRequest $request)
    {
        try {
            $this->companyService->storeAccessRule($request->all());

            return redirect()->route('company.show', $request->company_id)
                ->with('success', 'Regra de acesso criada com sucesso.');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Ocorreu um erro ao criar a regra de acesso: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function validateCompanyAccess(Request $request)
    {
        return $this->companyService->validateTryToAccess($request->all());
    }

    /**
     * Display the specified resource.
     */
    public function show(CompanyAccessRules $companyAccessRules)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(CompanyAccessRules $companyAccessRules)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCompanyAccessRulesRequest $request, CompanyAccessRules $companyAccessRules)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(CompanyAccessRules $companyAccessRules)
    {
        //
    }


}
