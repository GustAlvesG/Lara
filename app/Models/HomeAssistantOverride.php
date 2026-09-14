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
        'expires_at',
        'is_active',
        'is_quick',
        'created_by',
        'origin',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
        'expires_at' => 'datetime',
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
     * O que este agendamento manda fazer neste instante: true (ligar), false (desligar)
     * ou null quando ele não se pronuncia — e aí vale o próximo agendamento ou as reservas.
     *
     * No modo "Por horário", fora de todas as janelas o agendamento fica em silêncio.
     * Antes ele forçava "desligado" o dia inteiro, apagando a luz de quadras reservadas.
     *
     * Janelas são meio-abertas [início, fim): 18:00–20:00 e 20:00–22:00 não se sobrepõem.
     * A parte depois da meia-noite de uma janela 22:00–02:00 pertence ao dia anterior:
     * uma regra só de sexta cobre a madrugada de sábado, não a de sexta.
     */
    public function stateAt(Carbon $moment): ?bool
    {
        if (! $this->is_active) {
            return null;
        }

        // Comando manual com hora marcada: vencido, cala-se e a decisão volta
        // para agendamentos e reservas.
        if ($this->expires_at && $moment->greaterThanOrEqualTo($this->expires_at)) {
            return null;
        }

        if ($this->mode === 'manual_on' || $this->mode === 'manual_off') {
            return $this->appliesOn($moment) ? $this->mode === 'manual_on' : null;
        }

        foreach ($this->windows as $window) {
            $start = $moment->copy()->setTimeFromTimeString($window->turn_on_at);
            $end   = $moment->copy()->setTimeFromTimeString($window->turn_off_at);

            if ($end->lessThanOrEqualTo($start)) {
                $matches = ($moment->greaterThanOrEqualTo($start) && $this->appliesOn($moment))
                    || ($moment->lessThan($end) && $this->appliesOn($moment->copy()->subDay()));
            } else {
                $matches = $moment->greaterThanOrEqualTo($start)
                    && $moment->lessThan($end)
                    && $this->appliesOn($moment);
            }

            if ($matches) {
                return ($window->state ?? 'on') === 'on';
            }
        }

        return null;
    }

    /** Frase curta do que o agendamento faz, para listas e resumos. */
    public function getSummaryAttribute(): string
    {
        if ($this->mode === 'manual_on') {
            return 'Mantém ligado o dia todo';
        }

        if ($this->mode === 'manual_off') {
            return 'Mantém desligado o dia todo';
        }

        if ($this->windows->isEmpty()) {
            return 'Sem janelas de horário';
        }

        return $this->windows
            ->map(fn ($w) => (($w->state ?? 'on') === 'on' ? 'Liga' : 'Desliga')
                . ' ' . substr($w->turn_on_at, 0, 5) . '–' . substr($w->turn_off_at, 0, 5))
            ->join(' · ');
    }

    /** Agendamento já passou da data final (ou da hora, no comando manual)? */
    public function getIsExpiredAttribute(): bool
    {
        if ($this->expires_at && $this->expires_at->isPast()) {
            return true;
        }

        return (bool) ($this->end_date && $this->end_date->copy()->startOfDay()->lt(now()->startOfDay()));
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
