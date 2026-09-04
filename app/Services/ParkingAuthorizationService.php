<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\ParkingAuthorization;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use App\Models\Fleet\FleetVehicle;

class ParkingAuthorizationService
{
    public function store(array $data): ParkingAuthorization
    {
        return ParkingAuthorization::create([
            'plate'           => $this->normalizePlate($data['plate']),
            'name'            => $data['name'],
            'expiration_date' => $data['expiration_date'],
        ]);
    }

    public function update(array $data, ParkingAuthorization $authorization): ParkingAuthorization
    {
        $authorization->update([
            'plate'           => $this->normalizePlate($data['plate']),
            'name'            => $data['name'],
            'expiration_date' => $data['expiration_date'],
        ]);

        return $authorization;
    }

    /**
     * Duplicidade é avaliada na placa normalizada, senão "ABC-1234" e "ABC1234"
     * passariam pelo unique da coluna como registros diferentes.
     */
    public function plateExists(string $plate, ?int $ignoreId = null): bool
    {
        $normalized = $this->normalizePlate($plate);

        if ($normalized === '') {
            return false;
        }

        return ParkingAuthorization::query()
            ->whereRaw(
                "UPPER(REPLACE(REPLACE(REPLACE(plate, '-', ''), ' ', ''), '.', '')) = ?",
                [$normalized]
            )
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }

    /**
     * A lista que a câmera baixa para continuar decidindo com a API fora do ar.
     * Vai no formato final (placa, nome, validade em Y-m-d) porque as duas
     * origens — lista da diretoria e frota — não são o mesmo model.
     *
     * O carro da frota não tem validade: ele entra com uma data sintética
     * bem à frente, recalculada a cada consulta, para não mudar o formato que
     * o cliente já lê. Enquanto a câmera atualizar a lista, a data nunca
     * chega; quem tira o carro da liberação é a desativação no cadastro.
     */
    public function getValidAuthorizations(): Collection
    {
        $authorizations = ParkingAuthorization::where('expiration_date', '>=', Carbon::today())
            ->orderBy('plate')
            ->get(['plate', 'name', 'expiration_date'])
            ->map(fn (ParkingAuthorization $item) => [
                'plate'           => $item->plate,
                'name'            => $item->name,
                'expiration_date' => $item->expiration_date->toDateString(),
            ]);

        $syntheticExpiration = Carbon::today()
            ->addYears((int) config('fleet.gate_validity_years', 10))
            ->toDateString();

        $fleet = FleetVehicle::active()
            ->whereNotNull('plate')
            ->orderBy('plate')
            ->get(['plate', 'name'])
            ->map(fn (FleetVehicle $vehicle) => [
                'plate'           => $this->normalizePlate($vehicle->plate),
                'name'            => $vehicle->name,
                'expiration_date' => $syntheticExpiration,
            ]);

        // A placa da frota que também está na lista da diretoria aparece uma
        // vez só: a câmera casa por placa, e duas linhas iguais só confundem.
        return $authorizations->concat($fleet)->unique('plate')->values();
    }

    public function checkPlate(string $plate): array
    {
        $normalized = $this->normalizePlate($plate);

        if ($normalized === '') {
            return ['valid' => false, 'reason' => 'invalid_plate'];
        }

        // Carro da frota entra sempre: a autorização dele é ser da empresa, e
        // não uma validade que alguém precisa lembrar de renovar. Vem antes da
        // lista da diretoria justamente porque não tem data para conferir.
        if ($vehicle = $this->findFleetVehicleByPlate($normalized)) {
            return [
                'valid'           => true,
                'name'            => $vehicle->name,
                'reason'          => 'fleet_vehicle',
                'expiration_date' => null,
            ];
        }

        $authorization = $this->findByPlate($normalized);

        if (!$authorization) {
            return ['valid' => false, 'reason' => 'not_found'];
        }

        if ($authorization->expiration_date->lt(Carbon::today())) {
            return [
                'valid'           => false,
                'reason'          => 'expired',
                'expiration_date' => $authorization->expiration_date->toDateString(),
            ];
        }

        return [
            'valid'           => true,
            'name'            => $authorization->name,
            'expiration_date' => $authorization->expiration_date->toDateString(),
        ];
    }

    /**
     * Registra a tentativa de acesso vinda da câmera. Nunca pode derrubar a
     * resposta: o portão decide pelo status code.
     */
    public function logAccessAttempt(array $payload, array $result): void
    {
        try {
            Log::channel('parking_access')->info('Consulta de placa do LPR', [
                'plate'      => $this->normalizePlate((string) ($payload['plate'] ?? '')),
                'plate_raw'  => $this->auditValue($payload['plate'] ?? null),
                'camera'     => $this->auditValue($payload['camera'] ?? null),
                'time_entry' => $this->auditValue($payload['time_entry'] ?? null),
                'confidence' => $this->auditValue($payload['confidence'] ?? null),
                'valid'      => $result['valid'] ?? null,
                'reason'     => $result['reason'] ?? null,
            ]);
        } catch (\Throwable $e) {
            // Silencioso de propósito: falha de log não pode virar 4xx/5xx.
        }
    }

    /**
     * Os campos de auditoria chegam sem validação de tipo; achata para algo
     * que sempre serializa no log.
     */
    private function auditValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = is_scalar($value) ? (string) $value : json_encode($value);

        return mb_substr($value, 0, 120);
    }

    /**
     * Maiúscula e sem separadores, para tolerar variações do cliente
     * (ABC-1234, abc 1234, ABC1D23).
     */
    public function normalizePlate(string $plate): string
    {
        return preg_replace('/[^A-Z0-9]/', '', mb_strtoupper(trim($plate)));
    }

    /**
     * Busca pela placa já normalizada; o fallback cobre registros antigos
     * gravados com hífen, ponto ou espaço.
     */
    private function findByPlate(string $normalizedPlate): ?ParkingAuthorization
    {
        $authorization = ParkingAuthorization::where('plate', $normalizedPlate)->first();

        if ($authorization) {
            return $authorization;
        }

        return ParkingAuthorization::whereRaw(
            "UPPER(REPLACE(REPLACE(REPLACE(plate, '-', ''), ' ', ''), '.', '')) = ?",
            [$normalizedPlate]
        )->first();
    }

    /**
     * O veículo ativo da frota com esta placa. A comparação é na forma
     * normalizada dos dois lados: o cadastro grava sem separador, mas um
     * registro feito por seeder ou tinker pode ter escapado com hífen.
     *
     * Inativo não entra — veículo desativado saiu da frota, e a liberação da
     * cancela sai junto.
     */
    private function findFleetVehicleByPlate(string $normalizedPlate): ?FleetVehicle
    {
        return FleetVehicle::active()
            ->whereNotNull('plate')
            ->where(function ($query) use ($normalizedPlate) {
                $query->where('plate', $normalizedPlate)
                    ->orWhereRaw(
                        "UPPER(REPLACE(REPLACE(REPLACE(plate, '-', ''), ' ', ''), '.', '')) = ?",
                        [$normalizedPlate]
                    );
            })
            ->first();
    }
}
