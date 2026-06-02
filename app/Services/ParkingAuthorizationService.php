<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\ParkingAuthorization;

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

    public function checkPlate(string $plate): array
    {
        $authorization = ParkingAuthorization::where('plate', strtoupper($plate))->first();

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
}
