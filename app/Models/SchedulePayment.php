<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Schedule;
use App\Models\Status;
use App\Models\User;

class SchedulePayment extends Model
{
    /** @use HasFactory<\Database\Factories\SchedulePaymentFactory> */
    use HasFactory;

    protected $fillable = [
        'payment_method',
        'paid_amount',
        'paid_at',
        'payment_integration_id',
        'status_id',
        'refunded_amount',
        'refunded_by',
        'refunded_at',
    ];

    protected $casts = [
        'paid_amount' => 'decimal:2',
        'refunded_amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    //One Payment can be for many Schedules
    public function schedule()
    {
        return $this->hasMany(Schedule::class, 'schedule_payment_id', 'id');
    }

    //Alias mais explícito, preferir em código novo (schedule() é hasMany apesar do nome singular)
    public function schedules()
    {
        return $this->schedule();
    }

    public function status()
    {
        return $this->belongsTo(Status::class, 'status_id', 'id');
    }

    public function refunder()
    {
        return $this->belongsTo(User::class, 'refunded_by', 'id');
    }
}
