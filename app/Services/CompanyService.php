<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\Company\Company;
use App\Models\Company\CompanyWorker;
use App\Models\Company\CompanyAccessRule;
use App\Models\Company\CompanyAccessLog;
use App\Models\AppDriver;
use App\Models\UberAccessRequest;
use App\Services\RuleValidatorService;


class CompanyService
{
    public function getAllCompanies()
    {
        return Company::with(['workers', 'rules.weekdays'])->orderBy('name')->get();
    }

    public function getCompanyAccessStatus(Company $company): bool
    {
        return $this->validateRulesForAccess($company);
    }

    /**
     * Detalhes carregados sob demanda (accordion): funcionários com status de
     * acesso individual e regras da empresa. O cálculo de acesso por funcionário
     * fica fora do carregamento da listagem para não pesar a página.
     */
    public function getCompanyAccessDetails(Company $company): array
    {
        $company->load(['workers', 'rules.weekdays', 'rules.worker']);

        $workers = $company->workers->map(function (CompanyWorker $worker) use ($company) {
            return [
                'id'      => $worker->id,
                'name'    => $worker->name,
                'position'=> $worker->position,
                'allowed' => $this->validateRulesForAccess($company, $worker),
                'url'     => route('company.worker.show', [$company->id, $worker->id]),
            ];
        })->values();

        $rules = $company->rules->map(function (CompanyAccessRule $rule) {
            return [
                'id'          => $rule->id,
                'type'        => $rule->type,
                'description' => $rule->description,
                'worker'      => $rule->worker?->name,
                'start_date'  => $rule->start_date ? date('d/m/Y', strtotime($rule->start_date)) : null,
                'end_date'    => $rule->end_date ? date('d/m/Y', strtotime($rule->end_date)) : null,
                'start_time'  => $rule->start_time ? date('H:i', strtotime($rule->start_time)) : null,
                'end_time'    => $rule->end_time ? date('H:i', strtotime($rule->end_time)) : null,
                'weekdays'    => $rule->weekdays->pluck('short_name_pt')->values(),
            ];
        })->values();

        return [
            'workers' => $workers,
            'rules'   => $rules,
        ];
    }

    public function createCompany($request)
    {
        $data = $request->only(['name', 'telephone', 'email', 'address', 'description']);

        if ($request->hasFile('image')) {
            $data['image'] = $this->saveCompanyImage($request->file('image'));
        }

        $company = Company::create($data);
        return $company;
    }

    public function updateCompany($request, Company $company): Company
    {
        $data = $request->only(['name', 'telephone', 'email', 'address', 'description']);

        if ($request->hasFile('image')) {
            $data['image'] = $this->saveCompanyImage($request->file('image'));
        }

        $company->update($data);
        return $company;
    }

    private function saveCompanyImage($file): string
    {
        $imageName = 'company_' . time() . '.' . $file->getClientOriginalExtension();
        $file->move(public_path('images'), $imageName);
        return $imageName;
    }

    public function getCompanyDetails($company)
    {
        $company->load([
            'workers' => fn($q) => $q->with(['rules', 'latestAccessLog', 'creator', 'editor'])->orderBy('name'),
            'rules.weekdays',
            'rules.worker',
            'rules.creator',
            'rules.editor',
        ]);
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
            'document' => isset($data['document']) ? (preg_replace('/\D/', '', $data['document']) ?: null) : null,
            'image' => $data['image'] ?? null,
            'created_by_user' => auth()->id(),
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

        if (isset($fields['document'])) {
            $fields['document'] = preg_replace('/\D/', '', $fields['document']) ?: null;
        }

        if (!empty($data['image'])) {
            $fields['image'] = $this->saveBase64Image($data['image']);
        }

        $fields['updated_by_user'] = auth()->id();

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
            'created_by_user' => auth()->id(),
        ];

        $rule = $company->rules()->create($ruleData);

        if (isset($data['days']) && is_array($data['days'])) {
            $rule->weekdays()->sync($data['days']);
            $rule->load('weekdays');
        }

        return $rule;
    }

    public function updateAccessRule($data, CompanyAccessRule $rule): CompanyAccessRule
    {
        $fields = array_intersect_key($data, array_flip(['type', 'start_date', 'end_date', 'start_time', 'end_time', 'description']));
        $fields['updated_by_user'] = auth()->id();
        $rule->update($fields);

        if (isset($data['days']) && is_array($data['days'])) {
            $rule->weekdays()->sync($data['days']);
        } else {
            $rule->weekdays()->sync([]);
        }

        return $rule;
    }

    public function validateTryToAccess($data)
    {
        $target = $data['target'];

        if ($this->isUberPlateTarget($target)) {
            return $this->validateUberAccess($target);
        }

        $allWorkers = false;

        if (Str::startsWith($target, '*') || Str::endsWith($target, '*')) {
            $allWorkers = true;
            $target = str_replace('*', '', $target);
        }

        if ($this->isValidCPF($target)) {
            $normalized = preg_replace('/\D/', '', $target);
            $matchedWorkers = CompanyWorker::with('company')
                ->where('document', $normalized)
                ->orWhere('document', $target)
                ->get();

            if ($matchedWorkers->isEmpty()) {
                return ['found' => false, 'reason' => 'worker_not_found', 'workers' => []];
            }

            $response = [];
            foreach ($matchedWorkers as $worker) {
                $workerCompany = $worker->company;
                $allowed = $this->validateRulesForAccess($workerCompany, $worker);
                $response[] = [
                    'id'         => $worker->id,
                    'name'       => $worker->name,
                    'allowed'    => $allowed,
                    'image'      => $worker->image ? asset('images/' . $worker->image) : null,
                    'company_id' => $workerCompany->id,
                    'company'    => $workerCompany->name,
                ];
            }

            $first = $matchedWorkers->first();
            return [
                'found'      => true,
                'company_id' => $first->company->id,
                'company'    => $first->company->name,
                'workers'    => $response,
            ];
        }

        $company = Company::where('name', 'like', '%' . $target . '%')->first();
        if (!$company) {
            return ['found' => false, 'reason' => 'company_not_found', 'workers' => []];
        }

        $response = [];
        foreach ($company->workers()->get() as $worker) {
            $allowed = $this->validateRulesForAccess($company, $worker);
            $response[] = [
                'id'         => $worker->id,
                'name'       => $worker->name,
                'allowed'    => $allowed,
                'image'      => $worker->image ? asset('images/' . $worker->image) : null,
                'company_id' => $company->id,
                'company'    => $company->name,
            ];
        }

        return [
            'found'      => true,
            'company_id' => $company->id,
            'company'    => $company->name,
            'workers'    => $response,
        ];
    }

    /**
     * Endpoint único de registro de acesso. O tipo é definido pelo conteúdo do
     * target enviado no corpo:
     *   - CPF (terceirizado)          → funcionário da empresa parceira
     *   - PLACA.Nome.Obs              → motorista de aplicativo
     *   - texto livre (nome empresa)  → empresa parceira
     */
    public function registerAccess($data): array
    {
        $target = trim((string) ($data['target'] ?? ''));

        if ($this->isAppDriverTarget($target)) {
            return $this->registerAppDriverAccess($data);
        }

        if ($this->isUberPlateTarget($target)) {
            return $this->registerUberAccess($target);
        }

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
                'company_id'        => $worker['company_id'] ?? $result['company_id'],
                'company_worker_id' => $worker['id'],
                'target'            => $data['target'],
                'allowed'           => $worker['allowed'],
                'reason'            => $worker['allowed'] ? 'access_granted' : 'access_denied',
            ]);
        }

        return $result;
    }

    /**
     * Registra o acesso de um motorista de aplicativo a partir de um target no
     * formato "PLACA.NomeMotorista.Obs" (Obs é opcional). O veículo é cadastrado
     * automaticamente caso ainda não exista e o acesso é gravado no histórico.
     */
    public function registerAppDriverAccess(array $data): array
    {
        $parts = explode('.', $data['target'], 3);

        $plate = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $parts[0] ?? ''));
        $name  = trim($parts[1] ?? '');
        $obs   = isset($parts[2]) ? (trim($parts[2]) ?: null) : null;

        if ($plate === '' || $name === '') {
            return ['found' => false, 'reason' => 'invalid_target'];
        }

        $driver = AppDriver::firstOrCreate(
            ['plate' => $plate],
            ['name' => $name]
        );

        CompanyAccessLog::create([
            'company_id'        => null,
            'company_worker_id' => null,
            'app_driver_id'     => $driver->id,
            'target'            => $plate,
            'obs'               => $obs,
            'allowed'           => true,
            'reason'            => 'app_driver_access',
        ]);

        return [
            'found'    => true,
            'created'  => $driver->wasRecentlyCreated,
            'driver'   => [
                'id'    => $driver->id,
                'plate' => $driver->plate,
                'name'  => $driver->name,
                'obs'   => $obs,
            ],
        ];
    }

    /**
     * Valida o acesso de um Uber a partir da placa informada. O acesso é
     * considerado válido quando existe um pedido de Uber com a exata placa
     * cadastrada e ainda dentro da validade (expires_at no futuro).
     *
     * O retorno segue o mesmo formato do fluxo de terceirizados para que o
     * monitor de acesso consiga renderizar o resultado sem alterações.
     */
    public function validateUberAccess(string $target): array
    {
        $plate = $this->normalizePlate($target);

        $request = UberAccessRequest::where('vehicle_plate', $plate)
            ->where('status', UberAccessRequest::STATUS_AGUARDANDO_ACESSO)
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', now())
            ->latest('expires_at')
            ->first();

        if (!$request) {
            return [
                'found'   => false,
                'reason'  => 'uber_not_found',
                'type'    => 'uber',
                'plate'   => $plate,
                'workers' => [],
            ];
        }

        return [
            'found'      => true,
            'type'       => 'uber',
            'plate'      => $plate,
            'company_id' => null,
            'company'    => 'Uber · ' . $plate,
            'uber'       => [
                'id'             => $request->id,
                'requester_name' => $request->requester_name,
                'club_location'  => $request->club_location,
                'vehicle_plate'  => $request->vehicle_plate,
                'screenshot_url' => $request->screenshot_url,
                'expires_at'     => optional($request->expires_at)->toIso8601String(),
            ],
            'workers'    => [[
                'id'      => $request->id,
                'name'    => $request->requester_name ?: $plate,
                'allowed' => true,
                'image'   => null,
            ]],
        ];
    }

    /**
     * Valida e registra no histórico o acesso de um Uber. Marca accessed_at no
     * pedido correspondente e grava um CompanyAccessLog, mantendo o histórico
     * unificado com os demais tipos de acesso.
     */
    public function registerUberAccess(string $target): array
    {
        $result = $this->validateUberAccess($target);

        if ($result['found']) {
            // Acesso validado: conclui o pedido e vence a validade no mesmo
            // instante, impedindo que a mesma placa seja reutilizada.
            UberAccessRequest::where('id', $result['uber']['id'])
                ->update([
                    'status'      => UberAccessRequest::STATUS_CONCLUIDO,
                    'accessed_at' => now(),
                    'expires_at'  => now(),
                ]);
        }

        CompanyAccessLog::create([
            'company_id'        => null,
            'company_worker_id' => null,
            'target'            => $result['plate'],
            'allowed'           => $result['found'],
            'reason'            => $result['found'] ? 'uber_access_granted' : 'uber_not_found',
        ]);

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

    /**
     * Detecta o formato "PLACA.Nome.Obs": o primeiro segmento precisa ser uma
     * placa válida (Mercosul ABC1D23 ou antiga ABC1234) e precisa haver um nome.
     * CPF (que também contém pontos) não passa, pois "123" não é placa.
     */
    private function isAppDriverTarget(string $target): bool
    {
        $parts = explode('.', $target);

        if (count($parts) < 2 || trim($parts[1]) === '') {
            return false;
        }

        $plate = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $parts[0]));

        return (bool) preg_match('/^[A-Z]{3}[0-9][A-Z0-9][0-9]{2}$/', $plate)
            || (bool) preg_match('/^[A-Z]{3}[0-9]{4}$/', $plate);
    }

    /**
     * Detecta um target que é apenas uma placa (Mercosul ABC1D23 ou antiga
     * ABC1234), sem nome nem observação. É o formato usado para consultar o
     * acesso de Uber. CPF e nome de empresa não passam nesse teste.
     */
    private function isUberPlateTarget(string $target): bool
    {
        return $this->isPlate($this->normalizePlate($target));
    }

    private function normalizePlate(string $target): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $target));
    }

    private function isPlate(string $plate): bool
    {
        return (bool) preg_match('/^[A-Z]{3}[0-9][A-Z0-9][0-9]{2}$/', $plate)
            || (bool) preg_match('/^[A-Z]{3}[0-9]{4}$/', $plate);
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
