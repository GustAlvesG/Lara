<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Um acionamento de luz feito pelo sócio no app.
 *
 * A luz em si é o `home_assistant_overrides` apontado por
 * `home_assistant_override_id`; esta linha responde a duas perguntas que o
 * override não responde: de quem é a cota e quem acendeu.
 *
 * "Vigente" é ends_at no futuro e released_at nulo. Ninguém precisa encerrar
 * nada: passada a hora, a linha deixa de contar sozinha — do mesmo jeito que o
 * override expira sem ninguém desligar.
 */
class MemberLightingActivation extends Model
{
    protected $fillable = [
        'member_id',
        'place_id',
        'contactor_id',
        'home_assistant_override_id',
        'starts_at',
        'ends_at',
        'released_at',
        'origin',
    ];

    protected $casts = [
        'starts_at'   => 'datetime',
        'ends_at'     => 'datetime',
        'released_at' => 'datetime',
    ];

    public function place()
    {
        return $this->belongsTo(Place::class);
    }

    public function contactor()
    {
        return $this->belongsTo(Contactor::class);
    }

    public function override()
    {
        return $this->belongsTo(HomeAssistantOverride::class, 'home_assistant_override_id');
    }

    /** Acionamentos ainda valendo no instante informado. */
    public function scopeActiveAt($query, ?Carbon $moment = null)
    {
        $moment = $moment ?: Carbon::now();

        return $query->whereNull('released_at')->where('ends_at', '>', $moment);
    }

    public function isActiveAt(?Carbon $moment = null): bool
    {
        $moment = $moment ?: Carbon::now();

        return $this->released_at === null && $this->ends_at->greaterThan($moment);
    }

    /** Quanto ainda falta, em minutos — nunca negativo. */
    public function minutesRemaining(?Carbon $moment = null): int
    {
        $moment = $moment ?: Carbon::now();

        return $this->isActiveAt($moment) ? (int) ceil($moment->diffInSeconds($this->ends_at) / 60) : 0;
    }
}
