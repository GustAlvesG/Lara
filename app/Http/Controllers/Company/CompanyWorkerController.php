<?php

namespace App\Http\Controllers\Company;

use App\Models\CompanyWorker;
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
    public function create($id)
    {
        return view('companies.workers.create', ['companyId' => $id]);    
    }

    /**
     * Store a newly created resource in storage.
     */
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

    /**
     * Display the specified resource.
     */
    public function show(CompanyWorker $companyWorker)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(CompanyWorker $companyWorker)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCompanyWorkerRequest $request, CompanyWorker $companyWorker)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(CompanyWorker $companyWorker)
    {
        //
    }
}
