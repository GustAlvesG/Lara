<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParkingAccessLog extends Model
{
    protected $fillable = [
        'plate',
        'camera',
        'time_entry',
        'confidence',
        'authorized',
        'reason',
    ];

    protected $casts = [
        'time_entry' => 'datetime',
        'authorized' => 'boolean',
    ];
}
