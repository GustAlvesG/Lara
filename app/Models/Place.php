<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\PlaceGroup;
use App\Models\Schedule;

class Place extends Model
{
    use HasFactory;
    use SoftDeletes;

    
    protected $fillable = [
        'name',
        'image',
        'place_group_id',
        'price',
        'status_id',
    ];

    //Order by name
    protected static function booted()
    {
        static::addGlobalScope('order', function ($query) {
            $query->orderBy('name', 'asc');
        });
    }

    public function group()
    {
        return $this->belongsTo(PlaceGroup::class, 'place_group_id');
    }

    public function schedule()
    {
        return $this->hasMany(Schedule::class);
    }

    public function scheduleRules()
    {
        return $this->belongsToMany(ScheduleRules::class, 'place_schedule_rule', 'place_id', 'schedule_rule_id')
            ->withPivot('id')
            ->withTimestamps();
    }

}
