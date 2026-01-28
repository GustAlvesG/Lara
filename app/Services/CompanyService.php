<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\Company\Company;
use App\Models\Company\CompanyWorker;
use App\Models\Company\CompanyAccessRule;
use App\Services\RuleValidatorService;


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
        $company = $company->load('workers.rules', 'rules.weekdays');
        // dd($company->toArray());
        return $company;
    }

    public function storeWorker($data)
    {
        $company = Company::findOrFail($data['company_id']);
        
        $workerData = [
            'name' => $data['name'],
            'email' => $data['email'],
            'position' => $data['position'] ?? $data['role'] ?? 'Funcionário',
            'telephone' => $data['telephone'] ?? null,
            'document' => $data['document'] ?? null,
            'image' => $data['image'] ?? null,
        ];

        if (isset($data['image'])) {
            //Convert base64 to image and store
            $imageData = $data['image'];
            list($type, $imageData) = explode(';', $imageData);
            list(, $imageData) = explode(',', $imageData);
            $imageData = base64_decode($imageData);
            $imageName = 'worker_' . time() . '.jpg';
            file_put_contents(public_path('images/' . $imageName), $imageData);
            $workerData['image'] = $imageName;
        }
            
        $worker = $company->workers()->create($workerData);

        return $worker;
    }

    public function storeAccessRule($data) {
        $company = Company::findOrFail($data['company_id']);

        $ruleData = [
            'company_worker_id' => $data['company_worker_id'] ?? null,
            'company_id' => $data['company_id'],
            'type' => $data['type'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'] ?? null,
            'start_time' => $data['start_time'] ?? null,
            'end_time' => $data['end_time'] ?? null,
            'description' => $data['description'] ?? null,
        ];

        $rule = $company->rules()->create($ruleData);

        // Verifica se 'days' foi enviado e é um array, então sincroniza
        if (isset($data['days']) && is_array($data['days'])) {
            $rule->weekdays()->sync($data['days']);
            // Carrega os dados para retornar na resposta
            $rule->load('weekdays');
        }

        return $rule;
    }

    public function validateTryToAccess($data)
    {
        # Implement the logic to validate access based on the provided data


        $access_data = [
            'target' => $data['target'],
            'identifier_type' => null,
        ];

        $regex_cpf = '/^\d{11}\*?$/';
        $regex_name = '/^[A-Za-z0-9\s]{2,100}$/';


        if (preg_match($regex_cpf, $data['target'])) {
            $cpf = preg_replace('/\D/', '', $data['target']);
            // Validate CPF format
            if (!$this->isValidCPF($cpf)) {
                return false;
                }
            $access_data['identifier_type'] = 'cpf';
            if ($data['target'] != $cpf) {
                $access_data['identifier_type'] = 'cpf_company';
            }
            else
            $data['target'] = $cpf;
        } else if (preg_match($regex_name, $data['target'])) {
            $access_data['identifier_type'] = 'name';
        } else 
            return false;

        if (str_contains($access_data['identifier_type'], 'cpf')) {
            // Logic to validate access by CPF
            $worker = CompanyWorker::where('document', $data['target'])->first();
            if (!$worker) {
                return false;
            }
            $company = Company::where('id', $worker->company_id)->first();
            if (str_contains($access_data['identifier_type'], 'company')) {
                $all_workers = CompanyWorker::where('company_id', $company->id)
                    ->where('document', 'not', 'like', $data['target'] . '%')
                    ->get();
            } 

        } else  {
            // Logic to validate access by Name
            $company = Company::where('name', 'like', '%' . $data['target'] . '%')->first();
            if (!$company) {
                return false;
            }
            $worker = CompanyWorker::where('company_id', $company->id)
                ->where('name', 'like', '%' . $data['target'] . '%')
                ->get();
                
        } 
        return $this->validateRulesForAccess($company);
    }
    
    

    private function validateRulesForAccess(Company $company)
    {
        $response = true;
        $rules = $company->rules;
        if ($rules->isEmpty()) {
            $response = false;
        
        
        $debugg = [];   }

        //Order by type exclude rules first
        $rules = $rules->sortBy(function ($rule) {
            return $rule->type === 'exclude' ? 0 : 1;
        });

        foreach ($rules as $rule) {
            $validator = new RuleValidatorService();
            $response = $validator->validate($rule, []);
            $debugg[] = [
                'rule' => $rule->id,
                'type' => $rule->type,
                'response' => $response,
            ];

            if ($response === false && $rule->type === 'exclude') {
                break;
            }

        }
        dd("retorno", $response, $debugg);
    }

    private function isValidCPF($cpf)
    {
        // Remove non-numeric characters
        $cpf = preg_replace('/\D/', '', $cpf);

        // Check if the CPF has 11 digits
        if (strlen($cpf) != 11) {
            return false;
        }

        // Check for known invalid CPFs
        if (preg_match('/^(\\d)\\1{10}$/', $cpf)) {
            return false;
        }

        // Validate first digit
        for ($t = 9; $t < 11; $t++) {
            $d = 0;
            for ($c = 0; $c < $t; $c++) {
                $d += $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ($cpf[$c] != $d) {
                return false;
            }
        }

        return true;
    }
}