<?php

namespace App\Models\Ticket;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma entrada registrada de um ingresso. `day_key` é o id do dia (0 no evento
 * sem dias); o índice único (ticket, day_key) é o que garante uma entrada por
 * ingresso por dia, mesmo com dois registros ao mesmo tempo.
 */
class TicketCheckin extends Model
{
    protected $fillable = [
        'ticket_id',
        'ticket_event_day_id',
        'day_key',
        'checked_in_at',
        'checked_in_by',
        'source',
    ];

    protected $casts = [
        'ticket_id' => 'integer',
        'ticket_event_day_id' => 'integer',
        'day_key' => 'integer',
        'checked_in_at' => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function day(): BelongsTo
    {
        return $this->belongsTo(TicketEventDay::class, 'ticket_event_day_id');
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }
}
