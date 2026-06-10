<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class HomeAssistantOverride extends Model
{
    protected $fillable = [
        'name',
        'mode',
        'priority',
        'start_date',
        'end_date',
        'is_active',
        'is_quick',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
        'is_active'  => 'boolean',
        'is_quick'   => 'boolean',
        'priority'   => 'integer',
    ];

    public function contactors()
    {
        return $this->belongsToMany(Contactor::class, 'home_assistant_override_contactor');
    }

    public function weekdays()
    {
        return $this->belongsToMany(Weekday::class, 'home_assistant_override_weekday');
    }

    public function windows()
    {
        return $this->hasMany(HomeAssistantOverrideWindow::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * O agendamento está vigente na data informada?
     * (ativo, dentro do intervalo de datas e no dia da semana correto)
     */
    public function appliesOn(Carbon $moment): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $date = $moment->copy()->startOfDay();

        if ($this->start_date && $date->lt($this->start_date->copy()->startOfDay())) {
            return false;
        }

        if ($this->end_date && $date->gt($this->end_date->copy()->startOfDay())) {
            return false;
        }

        // Sem dias definidos = todos os dias
        if ($this->weekdays->isNotEmpty()) {
            // Carbon: 0 (domingo) .. 6 (sábado) — mesma ordem do seed da tabela weekdays (id 1 = domingo)
            $weekdayId = $moment->dayOfWeek + 1;
            if (! $this->weekdays->contains('id', $weekdayId)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Estado resultante do agendamento neste instante: true (ligar), false (desligar).
     * Pressupõe que appliesOn($moment) já é verdadeiro.
     */
    public function resolvedState(Carbon $moment): bool
    {
        if ($this->mode === 'manual_on') {
            return true;
        }

        if ($this->mode === 'manual_off') {
            return false;
        }

        // schedule_override: retorna o estado da primeira janela que contém o instante atual
        foreach ($this->windows as $window) {
            $start = $moment->copy()->setTimeFromTimeString($window->turn_on_at);
            $end   = $moment->copy()->setTimeFromTimeString($window->turn_off_at);

            // Janela que vira a meia-noite (ex.: 22:00 -> 02:00)
            $matches = $end->lessThanOrEqualTo($start)
                ? ($moment->greaterThanOrEqualTo($start) || $moment->lessThanOrEqualTo($end))
                : $moment->between($start, $end);

            if ($matches) {
                return $window->state === 'on';
            }
        }

        return false;
    }

    /** Agendamento já passou da data final? */
    public function getIsExpiredAttribute(): bool
    {
        return $this->end_date && $this->end_date->copy()->startOfDay()->lt(now()->startOfDay());
    }

    /** Rótulo legível do modo. */
    public function getModeLabelAttribute(): string
    {
        return match ($this->mode) {
            'manual_on'        => 'Forçar ligado',
            'manual_off'       => 'Forçar desligado',
            'schedule_override' => 'Por horário',
            default            => $this->mode,
        };
    }
}
