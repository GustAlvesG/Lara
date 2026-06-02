<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\Company\Company;
use App\Models\Company\CompanyWorker;
use App\Models\Company\CompanyAccessRule;
use App\Models\Company\CompanyAccessLog;
use App\Services\RuleValidatorService;


class CompanyService
{
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

    public function updateCompany($request, Company $company): Company
    {
        $data = $request->only(['name', 'telephone', 'email', 'address', 'description']);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('company_images', 'public');
        }

        $company->update($data);
        return $company;
    }

    public function getCompanyDetails($company)
    {
        $company = $company->load('workers.rules', 'rules.weekdays');
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

        if (isset($data['image']) && !empty($data['image'])) {
            $workerData['image'] = $this->saveBase64Image($data['image']);
        }

        $worker = $company->workers()->create($workerData);

        return $worker;
    }

    public function updateWorker(array $data, CompanyWorker $worker): CompanyWorker
    {
        $fields = array_intersect_key($data, array_flip(['name', 'email', 'position', 'telephone', 'document']));

        if (!empty($data['image'])) {
            $fields['image'] = $this->saveBase64Image($data['image']);
        }

        $worker->update($fields);
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

        if (isset($data['days']) && is_array($data['days'])) {
            $rule->weekdays()->sync($data['days']);
            $rule->load('weekdays');
        }

        return $rule;
    }

    public function validateTryToAccess($data)
    {
        $target = $data['target'];
        $allWorkers = false;

        if (Str::startsWith($target, '*') || Str::endsWith($target, '*')) {
            $allWorkers = true;
            $target = str_replace('*', '', $target);
        }

        $specificWorker = null;

        if ($this->isValidCPF($target)) {
            $normalized = preg_replace('/\D/', '', $target);
            $specificWorker = CompanyWorker::where('document', $normalized)
                ->orWhere('document', $target)
                ->first();
            if (!$specificWorker) {
                return ['found' => false, 'reason' => 'worker_not_found', 'workers' => []];
            }
            $company = $specificWorker->company;
        } else {
            $company = Company::where('name', 'like', '%' . $target . '%')->first();
            if (!$company) {
                return ['found' => false, 'reason' => 'company_not_found', 'workers' => []];
            }
        }

        $workers = $company->workers()->get();
        $response = [];

        foreach ($workers as $worker) {
            if ($specificWorker && $worker->id !== $specificWorker->id) {
                continue;
            }

            $allowed = $this->validateRulesForAccess($company, $worker);

            $response[] = [
                'id'      => $worker->id,
                'name'    => $worker->name,
                'allowed' => $allowed,
                'image'   => $worker->image ? asset('images/' . $worker->image) : null,
            ];
        }

        return [
            'found'      => true,
            'company_id' => $company->id,
            'company'    => $company->name,
            'workers'    => $response,
        ];
    }

    public function registerAccess($data): array
    {
        $result = $this->validateTryToAccess($data);

        if (!$result['found']) {
            CompanyAccessLog::create([
                'company_id'        => null,
                'company_worker_id' => null,
                'target'            => $data['target'],
                'allowed'           => false,
                'reason'            => $result['reason'],
            ]);

            return $result;
        }

        foreach ($result['workers'] as $worker) {
            CompanyAccessLog::create([
                'company_id'        => $result['company_id'],
                'company_worker_id' => $worker['id'],
                'target'            => $data['target'],
                'allowed'           => $worker['allowed'],
                'reason'            => $worker['allowed'] ? 'access_granted' : 'access_denied',
            ]);
        }

        return $result;
    }

    public function registerWorkerAccess(int $workerId): array
    {
        $worker = CompanyWorker::with('company')->find($workerId);

        if (!$worker) {
            return ['found' => false, 'reason' => 'worker_not_found', 'workers' => []];
        }

        $company = $worker->company;
        $allowed = $this->validateRulesForAccess($company, $worker);

        CompanyAccessLog::create([
            'company_id'        => $company->id,
            'company_worker_id' => $worker->id,
            'target'            => $worker->document ?? $worker->name,
            'allowed'           => $allowed,
            'reason'            => $allowed ? 'access_granted' : 'access_denied',
        ]);

        return [
            'found'      => true,
            'company_id' => $company->id,
            'company'    => $company->name,
            'workers'    => [[
                'id'      => $worker->id,
                'name'    => $worker->name,
                'allowed' => $allowed,
                'image'   => $worker->image ? asset('images/' . $worker->image) : null,
            ]],
        ];
    }

    private function validateRulesForAccess(Company $company, ?CompanyWorker $worker = null): bool
    {
        $ctx = [
            'current_date' => now()->toDateString(),
            'current_time' => now()->toTimeString(),
        ];
        $rv = new RuleValidatorService();

        $companyRules = $company->rules()->with('weekdays')->whereNull('company_worker_id')->get();
        $baseline = $this->applyRuleSet($companyRules, $rv, $ctx);

        if (!$worker) {
            return $baseline;
        }

        $workerRules = $company->rules()->with('weekdays')->where('company_worker_id', $worker->id)->get();

        if ($workerRules->isEmpty()) {
            return $baseline;
        }

        return $this->applyRuleSet($workerRules, $rv, $ctx);
    }

    private function applyRuleSet($rules, RuleValidatorService $rv, array $ctx): bool
    {
        $allowed = false;

        foreach ($rules->where('type', 'include') as $rule) {
            if ($rv->validate($rule, $ctx)) {
                $allowed = true;
                break;
            }
        }

        if ($allowed) {
            foreach ($rules->where('type', 'exclude') as $rule) {
                if ($rv->validate($rule, $ctx)) {
                    $allowed = false;
                    break;
                }
            }
        }

        return $allowed;
    }

    private function saveBase64Image(string $base64): string
    {
        list($type, $imageData) = explode(';', $base64);
        list(, $imageData) = explode(',', $imageData);
        $imageName = 'worker_' . time() . '.jpg';
        file_put_contents(public_path('images/' . $imageName), base64_decode($imageData));
        return $imageName;
    }

    private function isValidCPF($cpf)
    {
        $cpf = preg_replace('/\D/', '', $cpf);

        if (strlen($cpf) != 11) {
            return false;
        }

        if (preg_match('/^(\\d)\\1{10}$/', $cpf)) {
            return false;
        }

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
