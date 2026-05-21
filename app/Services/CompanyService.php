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
        ];
        $allWorkers = False;

        # $access_data['target'] can be a company name or worker cpf;
        # If it's a cpf, we need to find the worker and get the company_id
        # If it's a company name, we need to find the company_id directly using like for partial match
        # CPF can has a * in initial or final, so we need to remove it before validate the cpf
        # If CPF has a *, we need to validate only the numbers, and get all workers from the company and validate the rules for each worker, if any of them has a rule that allow the access, the access is allowed, if all of them has a rule that deny the access, the access is denied. If there is no rules, the access is allowed.
        if (Str::startsWith($access_data['target'], '*') || Str::endsWith($access_data['target'], '*')) {
            $allWorkers = True;
            $access_data['target'] = str_replace('*', '', $access_data['target']);
        }
                 
        if ($this->isValidCPF($access_data['target'])) {
            $worker = CompanyWorker::where('document', $access_data['target'])->first();
            if (!$worker) {
                return false; // Worker not found
            }
            $company = $worker->company;
            
        } else {
            
            $company = Company::where('name', 'like', '%' . $access_data['target'] . '%')->first();
            if (!$company) {
                return false; // Company not found
            }
   
        }

        $response = [];
        $valid = $this->validateRulesForAccess($company);
        $company->workers = $company->workers()->get();

        foreach ($company->workers as $worker) {
            $response[] = [
                'name' => $worker->name,
                'response' => $valid,
                'image' => $worker->image ? asset('images/' . $worker->image) : null,
                'id' => $worker->id,
            ];
        }

        return $response;
      
    }
    
    

    private function validateRulesForAccess(Company $company)
    {
        $response = false; // Default to false, access denied
        $rules = $company->rules()->with('weekdays')->get();

        $rules_include = $rules->where('type', 'include');
        $rules_exclude = $rules->where('type', 'exclude');
        $ruleValidator = new RuleValidatorService();
        // Verifica as regras de inclusão primeiro
        foreach ($rules_include as $rule) {
            if ($ruleValidator->validate($rule, ['current_date' => now()->toDateString(), 'current_time' => now()->toTimeString()])) {
                $response = true; // Access allowed if any include rule is valid
                break;
            }
        }
        // Se alguma regra de inclusão for válida, verificar se há regras de exclusão que possam negar o acesso
        if ($response) {
            foreach ($rules_exclude as $rule) {
                if ($ruleValidator->validate($rule, ['current_date' => now()->toDateString(), 'current_time' => now()->toTimeString()])) {
                    $response = false; // Access denied if any exclude rule is valid
                    break;
                }
            }
        }

        return $response;

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