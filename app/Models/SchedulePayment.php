<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Schedule;

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
    ];

    //One Payment can be for a many Schedules
    public function schedule()
    {
        return $this->hasMany(Schedule::class, 'schedule_payment_id', 'id');
    }
}
