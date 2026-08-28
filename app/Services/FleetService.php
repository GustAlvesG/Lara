<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Fleet\FleetTrip;
use App\Models\Fleet\FleetVehicle;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Quilometragem da frota própria.
 *
 * O ciclo é o mesmo do papel que a portaria preenchia à mão: na saída anota-se
 * quem levou o carro, qual carro, para onde e o hodômetro; na volta, só o
 * hodômetro. Cada anotação é uma metade da MESMA viagem — por isso uma linha
 * só, aberta na saída e fechada no retorno, e não dois registros soltos.
 *
 * Os horários são sempre o momento do registro (não se pergunta a hora ao
 * porteiro), com uma brecha para `registered_at` quando o lançamento chega
 * atrasado — fila offline da portaria, por exemplo.
 */
class FleetService
{
    /**
     * Cada recusa tem um código estável e um status HTTP fixo: o cliente da
     * portaria decide o que fazer pelo código, nunca pelo texto da mensagem.
     */
    public const ERROR_STATUS = [
        'vehicle_required'         => 422,
        'vehicle_not_found'        => 404,
        'vehicle_ambiguous'        => 422,
        'vehicle_inactive'         => 422,
        'vehicle_already_out'      => 409,
        'driver_required'          => 422,
        'driver_not_found'         => 404,
        'destination_required'     => 422,
        'odometer_required'        => 422,
        'odometer_below_last'      => 422,
        'odometer_below_departure' => 422,
        'odometer_jump_too_high'   => 422,
        'trip_not_found'           => 404,
        'trip_not_open'            => 409,
        'no_open_trip'             => 409,
        'return_before_departure'  => 422,
        'future_timestamp'         => 422,
    ];

    /* ---------------------------------------------------------------------
     | Consulta
     |--------------------------------------------------------------------*/

    /**
     * Os veículos com a situação de cada um. É o que a portaria pede ao abrir
     * a tela: qual carro está na garagem, qual está na rua e com quem.
     */
    public function vehiclesWithStatus(bool $includeInactive = false): Collection
    {
        return FleetVehicle::with(['openTrip.employee', 'openTrip.vehicle'])
            ->when(!$includeInactive, fn ($query) => $query->active())
            ->orderBy('name')
            ->get();
    }

    public function vehiclePayload(FleetVehicle $vehicle): array
    {
        $openTrip = $vehicle->openTrip;

        return [
            'id'               => $vehicle->id,
            'name'             => $vehicle->name,
            'plate'            => $vehicle->plate,
            'description'      => $vehicle->description,
            'active'           => $vehicle->active,
            'current_odometer' => $vehicle->current_odometer,
            'status'           => $openTrip ? 'out' : 'available',
            'status_label'     => $openTrip ? 'Em rota' : 'No Clube',
            'open_trip'        => $openTrip?->toApiArray(),
        ];
    }

    public function openTrips(): Collection
    {
        return FleetTrip::open()->with(['vehicle', 'employee'])->orderBy('departure_at')->get();
    }

    /**
     * Histórico com os filtros que a operação usa: veículo, motorista, período
     * e situação. Serve à tela e ao endpoint de listagem sem duplicar regra.
     */
    public function tripsQuery(array $filters = []): Builder
    {
        $query = FleetTrip::with(['vehicle', 'employee'])->latest('departure_at')->latest('id');

        if (!empty($filters['vehicle_id'])) {
            $query->where('fleet_vehicle_id', $filters['vehicle_id']);
        } elseif (!empty($filters['vehicle'])) {
            $resolved = $this->resolveVehicle(['vehicle' => $filters['vehicle']]);
            // Filtro que não resolve não pode virar "sem filtro": devolveria a
            // frota inteira como se fosse o resultado daquele veículo.
            $query->where('fleet_vehicle_id', $resolved['ok'] ? $resolved['vehicle']->id : 0);
        }

        if (!empty($filters['status']) && array_key_exists($filters['status'], FleetTrip::STATUS_LABELS)) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['driver'])) {
            $driver = trim((string) $filters['driver']);
            $query->where(function ($group) use ($driver) {
                $group->where('driver_name', 'like', "%{$driver}%")
                    ->orWhere('driver_document', 'like', "%{$driver}%");
            });
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('departure_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('departure_at', '<=', $filters['date_to']);
        }

        if (!empty($filters['only_alerts'])) {
            $query->where('odometer_alert', true);
        }

        return $query;
    }

    /**
     * Números do dia mais as pendências de baixa — a viagem esquecida em aberto
     * é o problema recorrente deste controle, então ela é um card, não um
     * detalhe do histórico.
     */
    public function stats(): array
    {
        $openTrips = $this->openTrips();
        $alertLimit = now()->subHours((int) config('fleet.open_trip_alert_hours', 12));

        return [
            'open'            => $openTrips->count(),
            'open_overdue'    => $openTrips->filter(fn (FleetTrip $trip) => $trip->departure_at->lt($alertLimit))->count(),
            'departures_today'=> FleetTrip::whereDate('departure_at', today())->where('status', '!=', FleetTrip::STATUS_CANCELED)->count(),
            'returns_today'   => FleetTrip::whereDate('return_at', today())->count(),
            'km_today'        => (int) FleetTrip::whereDate('return_at', today())->sum('distance_km'),
            'km_month'        => (int) FleetTrip::whereBetween('return_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('distance_km'),
        ];
    }

    /** Autocomplete de motorista na portaria: nome ou matrícula. */
    public function searchDrivers(string $term, int $limit = 10): array
    {
        $term = trim($term);

        if ($term === '') {
            return [];
        }

        $digits = preg_replace('/\D/', '', $term);

        return Employee::query()
            ->where(function ($query) use ($term, $digits) {
                $query->where('name', 'like', "%{$term}%")
                    ->orWhere('employee_code', 'like', "%{$term}%");

                if ($digits !== '') {
                    $query->orWhere('cpf', 'like', "%{$digits}%");
                }
            })
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (Employee $employee) => [
                'employee_id'   => $employee->id,
                'name'          => $employee->name,
                'employee_code' => $employee->employee_code,
                'department'    => $employee->department,
                'position'      => $employee->position,
            ])
            ->all();
    }

    /* ---------------------------------------------------------------------
     | Registro
     |--------------------------------------------------------------------*/

    /**
     * Saída: abre a viagem. Enquanto ela não for fechada, o mesmo veículo não
     * sai de novo — dois registros abertos para o mesmo carro significariam
     * que um dos dois hodômetros vai fechar contra a viagem errada.
     */
    public function registerDeparture(array $data): array
    {
        $vehicleResult = $this->resolveVehicle($data);

        if (!$vehicleResult['ok']) {
            return $vehicleResult;
        }

        $vehicle = $vehicleResult['vehicle'];

        if (!$vehicle->active) {
            return $this->fail('vehicle_inactive', "O veículo {$vehicle->name} está inativo e não pode registrar saída.");
        }

        if ($openTrip = $vehicle->openTrip) {
            return $this->fail(
                'vehicle_already_out',
                "O veículo {$vehicle->name} já saiu em {$openTrip->departure_at->format('d/m/Y H:i')} com {$openTrip->driver_name} e ainda não teve o retorno registrado.",
                ['trip' => $openTrip->toApiArray()]
            );
        }

        $driverResult = $this->resolveDriver($data);

        if (!$driverResult['ok']) {
            return $driverResult;
        }

        $destination = trim((string) ($data['destination'] ?? ''));

        if ($destination === '') {
            return $this->fail('destination_required', 'Informe para onde o veículo está indo.');
        }

        $odometer = $this->normalizeOdometer($data['odometer'] ?? null);

        if ($odometer === null) {
            return $this->fail('odometer_required', 'Informe a quilometragem de saída.');
        }

        $force = (bool) ($data['force'] ?? false);
        $alert = false;
        $lastOdometer = $vehicle->lastOdometer();

        if ($lastOdometer !== null && $odometer < $lastOdometer) {
            if (!$force) {
                return $this->fail(
                    'odometer_below_last',
                    "A quilometragem informada ({$odometer}) é menor que a última registrada para o {$vehicle->name} ({$lastOdometer}). Confira o número ou reenvie com force.",
                    ['last_odometer' => $lastOdometer]
                );
            }

            $alert = true;
        }

        $timestamp = $this->resolveTimestamp($data['registered_at'] ?? null);

        if (!$timestamp['ok']) {
            return $timestamp;
        }

        $trip = DB::transaction(function () use ($vehicle, $driverResult, $destination, $odometer, $timestamp, $data, $alert, $lastOdometer) {
            $trip = FleetTrip::create([
                'fleet_vehicle_id'        => $vehicle->id,
                'employee_id'             => $driverResult['employee']?->id,
                'driver_name'             => $driverResult['name'],
                'driver_document'         => $driverResult['document'],
                'destination'             => $destination,
                'departure_odometer'      => $odometer,
                'departure_at'            => $timestamp['at'],
                'departure_operator'      => $this->operator($data),
                'departure_registered_by' => auth()->id(),
                'departure_obs'           => $this->text($data['obs'] ?? null),
                'status'                  => FleetTrip::STATUS_OPEN,
                'odometer_alert'          => $alert,
            ]);

            // O espelho só anda para frente: uma saída aceita com hodômetro
            // menor (force) não pode rebaixar o piso das próximas viagens.
            $vehicle->update(['current_odometer' => max($odometer, (int) $lastOdometer)]);

            return $trip;
        });

        return [
            'ok'      => true,
            'trip'    => $trip->fresh(['vehicle', 'employee'])->toApiArray(),
            'vehicle' => $this->vehiclePayload($vehicle->fresh(['openTrip'])),
        ];
    }

    /**
     * Retorno: fecha a viagem aberta do veículo. A portaria não precisa saber o
     * id — manda o carro, e o sistema sabe qual viagem está esperando baixa.
     */
    public function registerReturn(array $data): array
    {
        $tripResult = $this->resolveOpenTrip($data);

        if (!$tripResult['ok']) {
            return $tripResult;
        }

        /** @var FleetTrip $trip */
        $trip = $tripResult['trip'];
        $vehicle = $trip->vehicle;

        $odometer = $this->normalizeOdometer($data['odometer'] ?? null);

        if ($odometer === null) {
            return $this->fail('odometer_required', 'Informe a quilometragem de retorno.');
        }

        $force = (bool) ($data['force'] ?? false);
        $alert = $trip->odometer_alert;
        $distance = null;

        if ($odometer < $trip->departure_odometer) {
            if (!$force) {
                return $this->fail(
                    'odometer_below_departure',
                    "A quilometragem de retorno ({$odometer}) é menor que a de saída ({$trip->departure_odometer}). Confira o número ou reenvie com force.",
                    ['departure_odometer' => $trip->departure_odometer, 'trip' => $trip->toApiArray()]
                );
            }

            $alert = true;
        } else {
            $distance = $odometer - $trip->departure_odometer;
            $maxTrip = (int) config('fleet.max_trip_km', 1500);

            if ($maxTrip > 0 && $distance > $maxTrip) {
                if (!$force) {
                    return $this->fail(
                        'odometer_jump_too_high',
                        "O retorno fecharia {$distance} km em uma viagem só, acima do limite de {$maxTrip} km. Confira o número ou reenvie com force.",
                        ['distance_km' => $distance, 'max_trip_km' => $maxTrip, 'trip' => $trip->toApiArray()]
                    );
                }

                $alert = true;
            }
        }

        $timestamp = $this->resolveTimestamp($data['registered_at'] ?? null);

        if (!$timestamp['ok']) {
            return $timestamp;
        }

        if ($timestamp['at']->lt($trip->departure_at)) {
            if (!$force) {
                return $this->fail(
                    'return_before_departure',
                    'O horário do retorno é anterior ao da saída.',
                    ['trip' => $trip->toApiArray()]
                );
            }

            $alert = true;
        }

        DB::transaction(function () use ($trip, $vehicle, $odometer, $distance, $timestamp, $data, $alert) {
            $trip->update([
                'return_odometer'      => $odometer,
                'return_at'            => $timestamp['at'],
                'return_operator'      => $this->operator($data),
                'return_registered_by' => auth()->id(),
                'return_obs'           => $this->text($data['obs'] ?? null),
                'distance_km'          => $distance,
                'status'               => FleetTrip::STATUS_CLOSED,
                'odometer_alert'       => $alert,
            ]);

            $vehicle->update(['current_odometer' => max($odometer, (int) $vehicle->current_odometer)]);
        });

        return [
            'ok'      => true,
            'trip'    => $trip->fresh(['vehicle', 'employee'])->toApiArray(),
            'vehicle' => $this->vehiclePayload($vehicle->fresh(['openTrip'])),
        ];
    }

    /**
     * Saída registrada por engano. Cancelar preserva a linha (o papel também
     * não era rasgado) e libera o veículo para um novo registro.
     */
    public function cancelTrip(FleetTrip $trip, ?string $reason = null): array
    {
        if (!$trip->isOpen()) {
            return $this->fail('trip_not_open', 'Só é possível cancelar uma viagem em aberto.');
        }

        $trip->update([
            'status'        => FleetTrip::STATUS_CANCELED,
            'canceled_at'   => now(),
            'cancel_reason' => $this->text($reason),
        ]);

        return ['ok' => true, 'trip' => $trip->fresh(['vehicle', 'employee'])->toApiArray()];
    }

    /* ---------------------------------------------------------------------
     | Cadastro de veículos
     |--------------------------------------------------------------------*/

    public function storeVehicle(array $data): FleetVehicle
    {
        return FleetVehicle::create($this->vehicleAttributes($data) + ['created_by_user' => auth()->id()]);
    }

    public function updateVehicle(array $data, FleetVehicle $vehicle): FleetVehicle
    {
        $vehicle->update($this->vehicleAttributes($data) + ['updated_by_user' => auth()->id()]);

        return $vehicle;
    }

    private function vehicleAttributes(array $data): array
    {
        $attributes = [
            'name'        => trim((string) $data['name']),
            'plate'       => FleetVehicle::normalizePlate($data['plate'] ?? '') ?: null,
            'description' => $this->text($data['description'] ?? null),
            'active'      => (bool) ($data['active'] ?? true),
        ];

        // A quilometragem inicial é um ponto de partida do cadastro; depois
        // dele quem manda são as viagens, e o campo em branco não zera nada.
        if (array_key_exists('current_odometer', $data) && $data['current_odometer'] !== null && $data['current_odometer'] !== '') {
            $attributes['current_odometer'] = (int) $data['current_odometer'];
        }

        return $attributes;
    }

    /* ---------------------------------------------------------------------
     | Resolução do que veio digitado
     |--------------------------------------------------------------------*/

    /**
     * O veículo pode chegar como id, placa ou nome — e o nome, sem acento e em
     * qualquer caixa: quem digita na portaria escreve "caminhao".
     *
     * @return array{ok: bool, vehicle?: FleetVehicle, error?: string, message?: string}
     */
    public function resolveVehicle(array $data): array
    {
        if (!empty($data['vehicle_id'])) {
            $vehicle = FleetVehicle::find($data['vehicle_id']);

            return $vehicle
                ? ['ok' => true, 'vehicle' => $vehicle]
                : $this->fail('vehicle_not_found', 'Veículo não encontrado.');
        }

        $raw = trim((string) ($data['vehicle'] ?? ''));

        if ($raw === '') {
            return $this->fail('vehicle_required', 'Informe o veículo.');
        }

        if (ctype_digit($raw)) {
            $vehicle = FleetVehicle::find((int) $raw);

            if ($vehicle) {
                return ['ok' => true, 'vehicle' => $vehicle];
            }
        }

        // A frota tem poucos veículos: comparar em memória evita depender de
        // collation do banco para acento e maiúscula.
        $vehicles = FleetVehicle::orderBy('name')->get();
        $plate = FleetVehicle::normalizePlate($raw);
        $needle = FleetVehicle::normalizeName($raw);

        if ($plate !== '') {
            $byPlate = $vehicles->first(fn (FleetVehicle $v) => $v->plate && FleetVehicle::normalizePlate($v->plate) === $plate);

            if ($byPlate) {
                return ['ok' => true, 'vehicle' => $byPlate];
            }
        }

        $exact = $vehicles->first(fn (FleetVehicle $v) => FleetVehicle::normalizeName($v->name) === $needle);

        if ($exact) {
            return ['ok' => true, 'vehicle' => $exact];
        }

        $partial = $vehicles->filter(fn (FleetVehicle $v) => $needle !== '' && str_contains(FleetVehicle::normalizeName($v->name), $needle));

        if ($partial->count() === 1) {
            return ['ok' => true, 'vehicle' => $partial->first()];
        }

        if ($partial->count() > 1) {
            return $this->fail(
                'vehicle_ambiguous',
                'Mais de um veículo corresponde a "' . $raw . '".',
                ['vehicles' => $partial->pluck('name')->values()->all()]
            );
        }

        return $this->fail(
            'vehicle_not_found',
            'Veículo "' . $raw . '" não encontrado.',
            ['vehicles' => $vehicles->where('active', true)->pluck('name')->values()->all()]
        );
    }

    /**
     * O motorista pode vir como matrícula, CPF ou nome. Quando bate com um
     * funcionário, o vínculo é guardado; quando não bate, o nome digitado vale
     * assim mesmo — nem todo mundo que leva o carro está na folha, e a portaria
     * não pode ficar impedida de registrar por causa disso.
     *
     * A exceção é um número que não encontrou ninguém: matrícula ou CPF errado
     * é erro de digitação, não um nome, e gravá-lo como motorista deixaria a
     * viagem sem dono identificável.
     *
     * @return array{ok: bool, employee?: ?Employee, name?: string, document?: ?string, error?: string, message?: string}
     */
    public function resolveDriver(array $data): array
    {
        $explicitName = trim((string) ($data['driver_name'] ?? ''));
        $raw = trim((string) ($data['driver'] ?? ''));

        if (!empty($data['employee_id'])) {
            $employee = Employee::find($data['employee_id']);

            if (!$employee) {
                return $this->fail('driver_not_found', 'Funcionário não encontrado.');
            }

            return ['ok' => true, 'employee' => $employee, 'name' => $employee->name, 'document' => $employee->cpf];
        }

        if ($raw === '' && $explicitName === '') {
            return $this->fail('driver_required', 'Informe quem está saindo com o veículo.');
        }

        if ($raw === '') {
            return ['ok' => true, 'employee' => null, 'name' => $explicitName, 'document' => null];
        }

        $digits = preg_replace('/\D/', '', $raw);
        $isNumeric = $digits !== '' && !preg_match('/[a-zA-Z]/', $raw);

        if ($isNumeric) {
            $employee = strlen($digits) === 11
                ? $this->findEmployeeByCpf($digits, $raw)
                : $this->findEmployeeByCode($raw, $digits);

            if ($employee) {
                return ['ok' => true, 'employee' => $employee, 'name' => $employee->name, 'document' => $employee->cpf];
            }

            if ($explicitName !== '') {
                return ['ok' => true, 'employee' => null, 'name' => $explicitName, 'document' => $digits];
            }

            return $this->fail(
                'driver_not_found',
                'Nenhum funcionário encontrado para "' . $raw . '". Envie o nome do motorista em driver_name para registrar mesmo assim.'
            );
        }

        $employee = $this->findEmployeeByName($raw);

        return [
            'ok'       => true,
            'employee' => $employee,
            'name'     => $employee?->name ?: ($explicitName ?: $raw),
            'document' => $employee?->cpf,
        ];
    }

    private function findEmployeeByCpf(string $digits, string $raw): ?Employee
    {
        return Employee::where('cpf', $digits)->orWhere('cpf', $raw)->first();
    }

    private function findEmployeeByCode(string $raw, string $digits): ?Employee
    {
        return Employee::where('employee_code', $raw)
            ->orWhere('employee_code', $digits)
            ->orWhere('employee_code', ltrim($digits, '0'))
            ->first();
    }

    /**
     * Nome só vincula quando não resta dúvida: homônimo ou busca parcial com
     * mais de um resultado grava o texto digitado sem vínculo, em vez de
     * apontar a viagem para o funcionário errado.
     */
    private function findEmployeeByName(string $name): ?Employee
    {
        $exact = Employee::where('name', $name)->get();

        if ($exact->count() === 1) {
            return $exact->first();
        }

        if ($exact->count() > 1) {
            return null;
        }

        $partial = Employee::where('name', 'like', "%{$name}%")->limit(2)->get();

        return $partial->count() === 1 ? $partial->first() : null;
    }

    /**
     * A viagem a fechar: por id, quando a portaria já sabe qual é, ou pela
     * viagem aberta do veículo informado.
     *
     * @return array{ok: bool, trip?: FleetTrip, error?: string, message?: string}
     */
    private function resolveOpenTrip(array $data): array
    {
        if (!empty($data['trip_id'])) {
            $trip = FleetTrip::with(['vehicle', 'employee'])->find($data['trip_id']);

            if (!$trip) {
                return $this->fail('trip_not_found', 'Viagem não encontrada.');
            }

            if (!$trip->isOpen()) {
                return $this->fail(
                    'trip_not_open',
                    'Esta viagem já foi ' . ($trip->status === FleetTrip::STATUS_CANCELED ? 'cancelada' : 'encerrada') . '.',
                    ['trip' => $trip->toApiArray()]
                );
            }

            return ['ok' => true, 'trip' => $trip];
        }

        $vehicleResult = $this->resolveVehicle($data);

        if (!$vehicleResult['ok']) {
            return $vehicleResult;
        }

        $vehicle = $vehicleResult['vehicle'];
        $trip = $vehicle->openTrip;

        if (!$trip) {
            return $this->fail(
                'no_open_trip',
                "Não há saída em aberto para o {$vehicle->name}.",
                ['vehicle' => $this->vehiclePayload($vehicle)]
            );
        }

        $trip->setRelation('vehicle', $vehicle);

        return ['ok' => true, 'trip' => $trip];
    }

    /**
     * O horário é o do registro. `registered_at` existe para o lançamento que
     * chega atrasado (fila offline, anotação recuperada do papel) e por isso
     * pode ser passado, mas nunca futuro além da folga de relógio.
     *
     * @return array{ok: bool, at?: CarbonInterface, error?: string, message?: string}
     */
    private function resolveTimestamp(?string $registeredAt): array
    {
        if (blank($registeredAt)) {
            return ['ok' => true, 'at' => now()];
        }

        try {
            $at = Carbon::parse($registeredAt);
        } catch (\Throwable) {
            return $this->fail('future_timestamp', 'Data/hora do registro inválida.');
        }

        $skew = (int) config('fleet.clock_skew_minutes', 5);

        if ($at->greaterThan(now()->addMinutes($skew))) {
            return $this->fail('future_timestamp', 'A data/hora do registro está no futuro.');
        }

        return ['ok' => true, 'at' => $at];
    }

    private function normalizeOdometer($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $digits = preg_replace('/\D/', '', (string) $value);

        return $digits === '' ? null : (int) $digits;
    }

    /** Quem registrou do lado da portaria — texto livre vindo do cliente. */
    private function operator(array $data): ?string
    {
        $operator = $this->text($data['operator'] ?? null);

        return $operator ?: (auth()->check() ? auth()->user()->name : null);
    }

    private function text($value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function fail(string $error, string $message, array $extra = []): array
    {
        return array_merge(['ok' => false, 'error' => $error, 'message' => $message], $extra);
    }
}
