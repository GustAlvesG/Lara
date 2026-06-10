<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\ParkingAuthorization;
use App\Models\ParkingAccessLog;

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

    public function checkPlate(array $data): array
    {
        $plate = strtoupper($data['plate']);
        $confidence = isset($data['confidence']) ? (int) $data['confidence'] : null;
        $authorization = ParkingAuthorization::where('plate', $plate)->first();

        if (!$authorization) {
            $this->storeAccessLog($plate, $data['camera'], $data['time_entry'], $confidence, false, 'not_found');
            return ['valid' => false, 'reason' => 'not_found'];
        }

        if ($authorization->expiration_date->lt(Carbon::today())) {
            $this->storeAccessLog($plate, $data['camera'], $data['time_entry'], $confidence, false, 'expired');
            return [
                'valid'           => false,
                'reason'          => 'expired',
                'expiration_date' => $authorization->expiration_date->toDateString(),
            ];
        }

        $this->storeAccessLog($plate, $data['camera'], $data['time_entry'], $confidence, true);

        return [
            'valid'           => true,
            'name'            => $authorization->name,
            'expiration_date' => $authorization->expiration_date->toDateString(),
        ];
    }

    public function storeAccessLog(string $plate, string $camera, string $timeEntry, ?int $confidence, bool $authorized, ?string $reason = null): void
    {
        if ($confidence >= 95 || $authorized) {
            ParkingAccessLog::create([
                'plate'      => $plate,
                'camera'     => $camera,
                'time_entry' => $timeEntry,
                'confidence' => $confidence,
                'authorized' => $authorized,
                'reason'     => $reason,
            ]);
        }
    }
}
