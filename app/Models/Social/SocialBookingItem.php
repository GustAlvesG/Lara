<?php

namespace App\Models\Social;

use Illuminate\Database\Eloquent\Model;

/**
 * Bebida somada a uma reserva social. Nome, unidade, código MV e preço são
 * cópia do catálogo no momento da pré-reserva: o que foi contratado não muda
 * quando a equipe reajusta o catálogo.
 */
class SocialBookingItem extends Model
{
    protected $fillable = [
        'social_booking_id',
        'social_beverage_id',
        'name',
        'unit',
        'mv_code',
        'unit_price',
        'quantity',
        'subtotal',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'quantity' => 'integer',
        'subtotal' => 'decimal:2',
    ];

    public function booking()
    {
        return $this->belongsTo(SocialBooking::class, 'social_booking_id');
    }

    public function beverage()
    {
        return $this->belongsTo(SocialBeverage::class, 'social_beverage_id')->withTrashed();
    }
}
