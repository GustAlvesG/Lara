<?php

namespace App\Models\Ticket;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Um dia de um evento de vários dias, com o horário próprio (pode passar da
 * meia-noite). A diária é um tipo de ingresso preso a um dia; o tipo sem dia,
 * num evento com dias, é o passaporte.
 *
 * A entrada do dia abre `tickets.checkin_hours_before` antes do início e fecha
 * no fim dele.
 */
class TicketEventDay extends Model
{
    private const WEEKDAYS = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

    protected $fillable = [
        'ticket_event_id',
        'name',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'ticket_event_id' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(TicketEvent::class, 'ticket_event_id');
    }

    public function types(): HasMany
    {
        return $this->hasMany(TicketType::class);
    }

    /** "Sábado (13/02)" ou "Sáb 13/02". */
    public function label(): string
    {
        $date = $this->starts_at->format('d/m');

        return filled($this->name)
            ? "{$this->name} ({$date})"
            : self::WEEKDAYS[$this->starts_at->dayOfWeek] . " {$date}";
    }

    /** "13/02 18:00 às 02:00". */
    public function hoursLabel(): string
    {
        return $this->starts_at->format('d/m H:i') . ' às ' . $this->ends_at->format($this->starts_at->isSameDay($this->ends_at) ? 'H:i' : 'd/m H:i');
    }

    public function checkinOpensAt(): Carbon
    {
        return $this->starts_at->copy()->subHours(max(0, (int) config('tickets.checkin_hours_before', 3)));
    }

    public function checkinOpen(?Carbon $at = null): bool
    {
        return ($at ?? now())->betweenIncluded($this->checkinOpensAt(), $this->ends_at);
    }
}
