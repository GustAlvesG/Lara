<?php

namespace App\Models\Ticket;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

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
 *
 * Lugar marcado (`ticket_map_id`): o tipo vende lugares do mapa (mesa,
 * bistrô, camarote) — todos, ou só os da `seat_category`. O ingresso é do
 * lugar, não é entrada: fica fora do "um por CPF", da lotação e da portaria.
 * É sempre do evento sem dias ou diária (lugar por dia): não há passaporte de
 * lugar. O mapa e a categoria não mudam depois de vendido.
 */
class TicketType extends Model
{
    public const VALIDITY_EVENT = 'event';
    public const VALIDITY_DAY = 'day';
    public const VALIDITY_PASSPORT = 'passport';

    /** O que o ingresso do tipo é: entrada (uma pessoa) ou lugar marcado (mesa…). */
    public const KIND_ENTRY = 'entry';
    public const KIND_SEAT = 'seat';

    protected $fillable = [
        'ticket_event_id',
        'ticket_event_day_id',
        'ticket_map_id',
        'seat_category',
        'name',
        'description',
        'sort_order',
        'active',
    ];

    protected $casts = [
        'ticket_event_id' => 'integer',
        'ticket_event_day_id' => 'integer',
        'ticket_map_id' => 'integer',
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

    public function map(): BelongsTo
    {
        return $this->belongsTo(TicketMap::class, 'ticket_map_id');
    }

    /** Tipo de lugar marcado (mesa, bistrô, camarote)? */
    public function isSeated(): bool
    {
        return $this->ticket_map_id !== null;
    }

    public function kind(): string
    {
        return $this->isSeated() ? self::KIND_SEAT : self::KIND_ENTRY;
    }

    /** O dia do lugar no índice único de ticket_seat_claims (0 = evento sem dias). */
    public function dayKey(): int
    {
        return $this->ticket_event_day_id ?? 0;
    }

    /**
     * Os lugares do mapa que este tipo vende (todos, ou os da categoria).
     *
     * @param  iterable<TicketSeat>  $seats
     * @return Collection<int, TicketSeat>
     */
    public function sellableSeats(iterable $seats): Collection
    {
        return collect($seats)->filter(fn (TicketSeat $seat) => $seat->active && $seat->soldBy($this))->values();
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
