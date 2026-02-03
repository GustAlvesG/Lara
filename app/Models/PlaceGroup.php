<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Place;
use App\Models\ScheduleRules;
use App\Models\Weekday;

class PlaceGroup extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name', 
        'category', 
        'image_vertical', 
        'image_horizontal', 
        'status',
        'vertices',
        'icon',
        'minimum_antecedence',
        'maximum_antecedence',
        'duration',
        'interval',
        'daily_limit',
        'start_time',
        'end_time',
        'start_time_sales',
        'end_time_sales'
    ];

    public function places()
    {
        return $this->hasMany(Place::class );
    }

    public function weekdays()
    {
        return $this->belongsToMany(Weekday::class, 'week_days_place_group', 'place_group_id', 'weekday_id')
            ->withPivot('id')
            ->withTimestamps();
    }
}
