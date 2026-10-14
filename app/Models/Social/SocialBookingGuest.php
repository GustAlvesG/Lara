<?php

namespace App\Models\Social;

use App\Models\Company\OneOffAccess;
use App\Support\Cpf;
use Illuminate\Database\Eloquent\Model;

/**
 * Convidado não sócio de uma reserva social. Sócio entra pela carteirinha e
 * não precisa estar aqui; o limite da lista é o `max_guests` do espaço.
 *
 * Com a reserva confirmada, cada convidado vira uma liberação pontual na data
 * do evento (`one_off_access_id`), que a portaria encontra pelo CPF no Monitor
 * de Acesso.
 */
class SocialBookingGuest extends Model
{
    protected $fillable = [
        'social_booking_id',
        'name',
        'cpf',
        'one_off_access_id',
    ];

    public function booking()
    {
        return $this->belongsTo(SocialBooking::class, 'social_booking_id');
    }

    public function oneOffAccess()
    {
        return $this->belongsTo(OneOffAccess::class, 'one_off_access_id');
    }

    public function maskedCpf(): string
    {
        return Cpf::mask($this->cpf);
    }

    public function formattedCpf(): string
    {
        return Cpf::format($this->cpf);
    }
}
