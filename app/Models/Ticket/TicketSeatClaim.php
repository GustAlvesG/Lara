<?php

namespace App\Models\Ticket;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * O lugar preso a um ingresso num dia (`day_key` = dia da diária, 0 no evento
 * sem dias). O índice único (lugar, day_key) impede a venda dupla mesmo se
 * alguma gravação escapar da trava do evento.
 *
 * A linha de um ingresso que deixou de ocupar (pedido vencido, desistência,
 * cancelamento) é apagada por TicketSeatService — na hora em que isso
 * acontece ou quando outra pessoa pede o mesmo lugar.
 */
class TicketSeatClaim extends Model
{
    protected $fillable = [
        'ticket_seat_id',
        'ticket_id',
        'day_key',
    ];

    protected $casts = [
        'ticket_seat_id' => 'integer',
        'ticket_id' => 'integer',
        'day_key' => 'integer',
    ];

    public function seat(): BelongsTo
    {
        return $this->belongsTo(TicketSeat::class, 'ticket_seat_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}
