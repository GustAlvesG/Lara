<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\PlaceGroup;

class ScheduleRules extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'status',
        'type',
        'duration',
        'weekdays',
        'antecedence',
        'start_time', 
        'end_time',
        'start_date',
        'end_date',
        'group_id',
    ];

    public function places()
    {
        return $this->belongsToMany(Place::class, 'place_schedule_rules', 'schedule_rules_id', 'place_id')
            ->withPivot('id')
            ->withTimestamps();
    }
    
}
