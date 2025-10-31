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
        'start_schedule',
        'end_schedule',
        'status',
        'price',
        'description',  
    ];
    

    //Place Has ONE
    public function place()
    {
        return $this->belongsTo(Place::class);
    }

    //Member Has ONE
    public function member()
    {
        return $this->belongsTo(Member::class);
    }

}
