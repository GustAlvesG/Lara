<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Place;
use App\Models\Member;
use App\Models\Status;
use App\Models\SchedulePayment;
use App\Models\Social\SocialBooking;

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
        'schedule_payment_id',
        'created_by_user',
        'updated_by_user',
        'cancel_reason',
        'cancelled_by',
        'cancelled_at',
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
        'cancelled_at' => 'datetime',
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

    /** Reserva de espaço social que esta ocupação representa, se for uma. */
    public function socialBooking()
    {
        return $this->hasOne(SocialBooking::class);
    }

    /**
     * Só as ocupações que não são de espaço social. A reserva social ocupa o
     * dia inteiro, e não pode contar como "o sócio já tem horário" nem ser
     * expirada pelo hold de 10 minutos das quadras.
     */
    public function scopeWithoutSocial($query)
    {
        return $query->whereNotExists(function ($sub) {
            $sub->selectRaw('1')
                ->from('social_bookings')
                ->whereColumn('social_bookings.schedule_id', 'schedules.id');
        });
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_user', 'id');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'updated_by_user', 'id');
    }

    public function canceller()
    {
        return $this->belongsTo(User::class, 'cancelled_by', 'id');
    }

}
