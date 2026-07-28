<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\ParkingAuthorization;
use Illuminate\Support\Facades\Log;

class ParkingAuthorizationService
{
    public function store(array $data): ParkingAuthorization
    {
        return ParkingAuthorization::updateOrCreate(
            ['plate' => strtoupper($data['plate'])],
            [
                'name'            => $data['name'],
                'expiration_date' => $data['expiration_date'],
            ]
        );
    }

    public function update(array $data, ParkingAuthorization $authorization): ParkingAuthorization
    {
        $authorization->update([
            'plate'           => strtoupper($data['plate']),
            'name'            => $data['name'],
            'expiration_date' => $data['expiration_date'],
        ]);

        return $authorization;
    }

    public function getValidAuthorizations()
    {
        return ParkingAuthorization::where('expiration_date', '>=', Carbon::today())
            ->orderBy('plate')
            ->get(['plate', 'name', 'expiration_date']);
    }

    public function checkPlate(string $plate): array
    {
        $normalized = $this->normalizePlate($plate);

        if ($normalized === '') {
            return ['valid' => false, 'reason' => 'invalid_plate'];
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
}
