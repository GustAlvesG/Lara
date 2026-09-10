<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Férias ou afastamento de um funcionário.
 *
 * Registro **informativo**: não interfere na importação do espelho de ponto
 * nem no cálculo de saldo do Banco de Horas. Serve ao cadastro do RH e ao
 * módulo de consulta de funcionários ativos que vem depois.
 *
 * `end_date` nula quer dizer **em aberto** — o período vale de `start_date` em
 * diante até alguém fechá-lo. É o estado normal de um afastamento recém
 * registrado, quando ainda não se sabe a data de retorno.
 */
class EmployeeAbsence extends Model
{
    use HasFactory;

    public const TYPE_VACATION = 'vacation';
    public const TYPE_LEAVE    = 'leave';

    public const TYPES = [
        self::TYPE_VACATION => 'Férias',
        self::TYPE_LEAVE    => 'Afastamento',
    ];

    protected $table = 'employee_absences';

    protected $fillable = [
        'employee_id',
        'type',
        'start_date',
        'end_date',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /** Rótulo em português do tipo, para as telas. */
    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /** Período ainda sem data de fim. */
    public function isOpenEnded(): bool
    {
        return $this->end_date === null;
    }

    /** O período cobre a data informada? Sem `end_date`, cobre dali em diante. */
    public function coversDate($date): bool
    {
        $date = \Carbon\Carbon::parse($date)->startOfDay();

        if ($this->start_date->gt($date)) {
            return false;
        }

        return $this->end_date === null || $this->end_date->gte($date);
    }

    /** Períodos vigentes na data informada (hoje, por padrão). */
    public function scopeCurrent(Builder $query, $date = null): Builder
    {
        $date = ($date ? \Carbon\Carbon::parse($date) : now())->startOfDay()->toDateString();

        return $query->where('start_date', '<=', $date)
            ->where(function (Builder $q) use ($date) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $date);
            });
    }
}
