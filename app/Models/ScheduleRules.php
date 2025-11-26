<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\PlaceGroup;
use App\Models\Place;
use App\Models\Weekday;

class ScheduleRules extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'status',
        'type',
        'duration',
        'minimum_antecedence',
        'maximum_antecedence',
        'interval',
        'start_time', 
        'end_time',
        'start_date',
        'end_date',
        'quantity',
    ];

    protected $with = [
        'weekdays'
    ];


    public function places()
    {
        return $this->belongsToMany(Place::class, 'place_schedule_rule', 'schedule_rule_id', 'place_id')
            ->withPivot('id')
            ->withTimestamps();
    }

    public function weekdays()
    {
        return $this->belongsToMany(Weekday::class, 'week_days_schedule_rule', 'schedule_rule_id', 'weekday_id')
            ->withPivot('id')
            ->withTimestamps();
    }
    
}
