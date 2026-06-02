<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParkingAuthorization extends Model
{
    use HasFactory;

    protected $fillable = [
        'plate',
        'name',
        'expiration_date',
    ];

    protected $casts = [
        'expiration_date' => 'date',
    ];
}
