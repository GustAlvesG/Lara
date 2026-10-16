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
        'category',
        'name',
        'unit',
        'mv_code',
        'unit_price',
        'quantity',
        'subtotal',
        'tables_per_unit',
        'chairs_per_unit',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'quantity' => 'integer',
        'subtotal' => 'decimal:2',
        'tables_per_unit' => 'integer',
        'chairs_per_unit' => 'integer',
    ];

    public function isFurniture(): bool
    {
        return $this->category === SocialBeverage::CATEGORY_FURNITURE;
    }

    public function booking()
    {
        return $this->belongsTo(SocialBooking::class, 'social_booking_id');
    }

    public function beverage()
    {
        return $this->belongsTo(SocialBeverage::class, 'social_beverage_id')->withTrashed();
    }
}
