<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Place;
use App\Models\Member;
use App\Models\Status;
use App\Models\SchedulePayment;

class Schedule extends Model
{
    use HasFactory;


    protected $fillable = [
        'place_id',
        'member_id',
        'start_schedule',
        'end_schedule',
        'status_id',
        'price',
        'description',  
    ];

    //By default, ignore status_id = 4, Expired
    protected static function booted()
    {
        static::addGlobalScope('not_expired', function ($builder) {
            $builder->where('status_id', '!=', 4);
        });
    }

    protected $casts = [
        'start_schedule' => 'datetime',
        'end_schedule' => 'datetime',
        'price' => 'decimal:2',
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

    //Status Has ONE
    public function status()
    {
        return $this->belongsTo(Status::class, 'status_id', 'id');
    }

    public function schedulePayment()
    {
        return $this->belongsTo(SchedulePayment::class, 'schedule_payment_id', 'id');
    }

}
