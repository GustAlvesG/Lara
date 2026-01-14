<?php

namespace App\Http\Controllers\Company;

use App\Models\CompanyAccessRules;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCompanyAccessRulesRequest;
use App\Http\Requests\UpdateCompanyAccessRulesRequest;

class CompanyAccessRulesController extends Controller
{
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
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCompanyAccessRulesRequest $request)
    {
        //
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
