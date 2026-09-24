<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Exceção de calendário do autoatendimento de iluminação: um feriado liberado
 * ou um dia bloqueado.
 *
 * Quem consulta é SelfServiceLightingService::windowFor(), antes da regra
 * semanal de config/home_assistant.php — a data sempre vence o dia da semana.
 */
class LightingSelfServiceDate extends Model
{
    public const MODE_ALLOW = 'allow';
    public const MODE_BLOCK = 'block';

    protected $fillable = [
        'date',
        'mode',
        'starts_at',
        'ends_at',
        'reason',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeForDate($query, Carbon $date)
    {
        return $query->whereDate('date', $date->toDateString());
    }

    public function isBlock(): bool
    {
        return $this->mode === self::MODE_BLOCK;
    }

    /** Rótulo do painel — o motivo quando existe, senão o que a linha faz. */
    public function label(): string
    {
        return $this->reason ?: ($this->isBlock() ? 'Bloqueado' : 'Liberado');
    }
}
