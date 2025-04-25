<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Schedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'place_id',
        'member_id',
        'start_time'
        
        
    ];

    //Place Has ONE


}
