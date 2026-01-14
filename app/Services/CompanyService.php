<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\Company\Company;

class CompanyService
{
    // Company related service methods would go here
    public function getAllCompanies()
    {
        return Company::all();
    }

    public function createCompany($request)
    {
        $data = $request->only(['name', 'telephone', 'email', 'address', 'description']);

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('company_images', 'public');
            $data['image'] = $imagePath;
        }

        $company = Company::create($data);
        return $company;
    }

    public function getCompanyDetails($company)
    {
        $company = $company->load('workers.rules', 'rules');
        return $company;
    }

    public function storeWorker($data)
    {
        $company = Company::findOrFail($data['company_id']);
        

        $workerData = [
            'name' => $data['name'],
            
        ];

        dd($company, $data, $workerData);
        $worker = $company->workers()->create($workerData);

        return $worker;
    }
}