<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Contactor extends Model
{
    protected $fillable = ['name', 'entity_id'];

    public function places()
    {
        return $this->hasMany(Place::class);
    }

    public function overrides()
    {
        return $this->belongsToMany(HomeAssistantOverride::class, 'home_assistant_override_contactor');
    }

    /**
     * Agendamentos ativos vinculados a este contator, mais prioritários primeiro.
     * Eager-load com: ->with(['overrides' => fn($q) => $q->where('is_active', true)->with(['weekdays','windows'])])
     */
    public function activeOverrides()
    {
        return $this->overrides()
            ->where('is_active', true)
            ->orderByDesc('priority')
            ->orderByDesc('home_assistant_overrides.id');
    }

    /**
     * Agendamento que decide o estado no instante informado: o de maior prioridade
     * que se pronuncia (ver HomeAssistantOverride::stateAt). Retorna null se nenhum
     * se pronuncia → cai no agendamento padrão (reservas).
     */
    public function effectiveOverride(?Carbon $moment = null): ?HomeAssistantOverride
    {
        $moment = $moment ?: Carbon::now();

        return $this->overrides
            ->where('is_active', true)
            ->sortBy([['priority', 'desc'], ['id', 'desc']])
            ->first(fn ($override) => $override->stateAt($moment) !== null);
    }
}
