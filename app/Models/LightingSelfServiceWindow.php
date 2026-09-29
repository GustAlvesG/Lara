<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Um horário de autoatendimento: o padrão do clube, ou a exceção de uma quadra.
 *
 * `place_id` nulo é o padrão; preenchido, é a quadra. Quem resolve a
 * precedência (quadra → padrão → configuração) é
 * SelfServiceLightingService::rangeFor().
 */
class LightingSelfServiceWindow extends Model
{
    /**
     * Dia liberado em `lighting_self_service_dates` sem horário próprio.
     *
     * Fora da faixa 0–6 do Carbon de propósito: é um "dia" que o calendário não
     * tem, mas que o clube trata como categoria própria de horário.
     */
    public const WEEKDAY_HOLIDAY = 7;

    /** Domingo (0) a sábado (6), mais o feriado. */
    public const WEEKDAYS = [0, 1, 2, 3, 4, 5, 6, self::WEEKDAY_HOLIDAY];

    protected $fillable = [
        'place_id',
        'weekday',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'weekday' => 'integer',
    ];

    public function place()
    {
        return $this->belongsTo(Place::class);
    }

    /** O padrão do clube, não a exceção de uma quadra. */
    public function isDefault(): bool
    {
        return $this->place_id === null;
    }

    /**
     * Linha que manda fechar.
     *
     * Só faz sentido numa exceção de quadra: é como se cala uma quadra num dia
     * em que o padrão do clube abre. No padrão, nulo é o mesmo que não existir.
     */
    public function isClosed(): bool
    {
        return $this->starts_at === null || $this->ends_at === null;
    }

    /** @return array{0: string, 1: string}|null */
    public function range(): ?array
    {
        return $this->isClosed() ? null : [$this->starts_at, $this->ends_at];
    }

    public static function weekdayName(int $weekday): string
    {
        return [
            0 => 'Domingo', 1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta',
            4 => 'Quinta',  5 => 'Sexta',   6 => 'Sábado',
            self::WEEKDAY_HOLIDAY => 'Feriado',
        ][$weekday] ?? 'Dia ' . $weekday;
    }
}
