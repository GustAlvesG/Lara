<?php

namespace App\Models\Ticket;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tipo de ingresso do evento (Pista, Camarote, Infantil…). Os preços e as
 * quantidades ficam nos lotes do tipo; o lote vigente sai do
 * TicketInventory.
 *
 * Tipo com ingresso vendido não é apagado: é desativado (`active`).
 *
 * Validade (`validity()`): no evento sem dias, o ingresso vale o evento; no
 * evento de vários dias, o tipo com dia é a diária daquele dia e o tipo sem
 * dia é o passaporte (todos os dias). O dia não muda depois de vendido.
 */
class TicketType extends Model
{
    public const VALIDITY_EVENT = 'event';
    public const VALIDITY_DAY = 'day';
    public const VALIDITY_PASSPORT = 'passport';

    protected $fillable = [
        'ticket_event_id',
        'ticket_event_day_id',
        'name',
        'description',
        'sort_order',
        'active',
    ];

    protected $casts = [
        'ticket_event_id' => 'integer',
        'ticket_event_day_id' => 'integer',
        'active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $attributes = [
        'active' => true,
        'sort_order' => 0,
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(TicketEvent::class, 'ticket_event_id');
    }

    public function day(): BelongsTo
    {
        return $this->belongsTo(TicketEventDay::class, 'ticket_event_day_id');
    }

    public function validity(): string
    {
        if ($this->ticket_event_day_id !== null) {
            return self::VALIDITY_DAY;
        }

        return $this->event->hasDays() ? self::VALIDITY_PASSPORT : self::VALIDITY_EVENT;
    }

    /** "Sáb 13/02", "Passaporte (todos os dias)" ou null (evento sem dias). */
    public function validityLabel(): ?string
    {
        return match ($this->validity()) {
            self::VALIDITY_DAY => $this->day->label(),
            self::VALIDITY_PASSPORT => 'Passaporte (todos os dias)',
            default => null,
        };
    }

    /** O nome com a validade, para listas e e-mails: "Pista · Sáb 13/02". */
    public function fullName(): string
    {
        $label = $this->validityLabel();

        return $label ? "{$this->name} · {$label}" : $this->name;
    }

    /**
     * Dois ingressos se cruzam (a mesma pessoa não pode ter os dois)? Dia nulo
     * = todos os dias (passaporte, ou o evento sem dias).
     */
    public static function daysOverlap(?int $a, ?int $b): bool
    {
        return $a === null || $b === null || $a === $b;
    }

    public function lots(): HasMany
    {
        return $this->hasMany(TicketLot::class)->orderBy('sequence')->orderBy('id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}
