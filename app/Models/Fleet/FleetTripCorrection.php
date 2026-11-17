<?php

namespace App\Models\Fleet;

use Illuminate\Database\Eloquent\Model;

/**
 * Uma correção de km feita em SIV › Viagens: o antes e o depois da viagem,
 * quem corrigiu e o motivo. Só se cria, nunca se edita.
 */
class FleetTripCorrection extends Model
{
    protected $fillable = [
        'fleet_trip_id',
        'old_departure_odometer',
        'new_departure_odometer',
        'old_return_odometer',
        'new_return_odometer',
        'old_distance_km',
        'new_distance_km',
        'reason',
        'user_id',
        'user_name',
    ];

    protected $casts = [
        'old_departure_odometer' => 'integer',
        'new_departure_odometer' => 'integer',
        'old_return_odometer'    => 'integer',
        'new_return_odometer'    => 'integer',
        'old_distance_km'        => 'integer',
        'new_distance_km'        => 'integer',
    ];

    public function trip()
    {
        return $this->belongsTo(FleetTrip::class, 'fleet_trip_id');
    }

    /** "Saída 452.100 → 45.210; retorno 452.600 → 45.260", só o que mudou. */
    public function summary(): string
    {
        $km = fn (?int $value) => $value === null ? '—' : number_format($value, 0, ',', '.');
        $parts = [];

        if ($this->old_departure_odometer !== $this->new_departure_odometer) {
            $parts[] = 'saída ' . $km($this->old_departure_odometer) . ' → ' . $km($this->new_departure_odometer);
        }

        if ($this->old_return_odometer !== $this->new_return_odometer) {
            $parts[] = 'retorno ' . $km($this->old_return_odometer) . ' → ' . $km($this->new_return_odometer);
        }

        return ucfirst(implode('; ', $parts));
    }
}
