<?php

namespace App\Http\Controllers\Company;

use App\Models\Company\Company;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCompanyRequest;
use App\Http\Requests\UpdateCompanyRequest;
use Illuminate\Validation\ValidationException;
use App\Services\CompanyService;

class CompanyController extends Controller
{
    public function __construct()
    {
        $this->companyService = new CompanyService();
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $companies = $this->companyService->getAllCompanies();
        return view('companies.index', compact('companies'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('companies.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCompanyRequest $request)
    {
        try {
            $company = $this->companyService->createCompany($request);
            //Validation is already handled by StoreCompanyRequest
        } catch (ValidationException $e) {
            //Return back with validation errors in 'message' variable
            return redirect()->back()->withErrors($e->errors())->withInput()->with('error', 'Erro de validação: ' . implode(' ', array_map(function($fieldErrors) {
                return implode(' ', $fieldErrors);
            }, $e->errors())));
        }
        return redirect()->route('company.index')->with('success', 'Parceiro Terceirizado criado com sucesso.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Company $company)
    {
        try {
            $companyDetails = $this->companyService->getCompanyDetails($company);
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->with('error', 'Erro ao carregar detalhes da empresa: ' . implode(' ', array_map(function($fieldErrors) {
                return implode(' ', $fieldErrors);
            }, $e->errors())));
        }

        return view('companies.show', compact('companyDetails'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Company $company)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCompanyRequest $request, Company $company)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Company $company)
    {
        //
    }
}
