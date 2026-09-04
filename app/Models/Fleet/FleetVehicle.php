<?php

namespace App\Models\Fleet;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class FleetVehicle extends Model
{
    protected $fillable = [
        'name',
        'plate',
        'description',
        'current_odometer',
        'active',
        'created_by_user',
        'updated_by_user',
    ];

    protected $casts = [
        'active'           => 'boolean',
        'current_odometer' => 'integer',
    ];

    public function trips()
    {
        return $this->hasMany(FleetTrip::class);
    }

    /**
     * A viagem em aberto — no máximo uma por veículo, garantido no serviço:
     * o carro só sai de novo depois de alguém dar a baixa do retorno.
     */
    public function openTrip()
    {
        return $this->hasOne(FleetTrip::class)->where('status', FleetTrip::STATUS_OPEN)->latestOfMany();
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function isOut(): bool
    {
        return $this->openTrip !== null;
    }

    /**
     * Piso do próximo hodômetro: o maior número já registrado para o veículo.
     * O espelho da tabela costuma bastar, mas uma viagem editada à mão pode
     * deixá-lo para trás, então a última viagem também entra na conta.
     */
    public function lastOdometer(): ?int
    {
        $lastTrip = $this->trips()
            ->where('status', '!=', FleetTrip::STATUS_CANCELED)
            ->orderByDesc('departure_at')
            ->orderByDesc('id')
            ->first();

        return collect([
            $this->current_odometer,
            $lastTrip?->return_odometer,
            $lastTrip?->departure_odometer,
        ])->filter(fn ($value) => $value !== null)->max();
    }

    /**
     * Chave de comparação para o nome digitado na portaria: sem acento, sem
     * caixa e sem pontuação, para "caminhao" achar "Caminhão".
     */
    public static function normalizeName(?string $name): string
    {
        return Str::of((string) $name)->ascii()->lower()->replaceMatches('/[^a-z0-9]/', '')->value();
    }

    public static function normalizePlate(?string $plate): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $plate));
    }
}
