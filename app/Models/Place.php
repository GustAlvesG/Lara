<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\PlaceGroup;
use App\Models\Schedule;
use App\Models\Contactor;

class Place extends Model
{
    use HasFactory;
    use SoftDeletes;

    
    protected $fillable = [
        'name',
        'contactor_id',
        'self_service_lighting',
        'image',
        'place_group_id',
        'price',
        'status_id',
    ];

    protected $casts = [
        'self_service_lighting' => 'boolean',
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

    public function contactor()
    {
        return $this->belongsTo(Contactor::class);
    }

    public function schedule()
    {
        return $this->hasMany(Schedule::class);
    }

    /**
     * Espaços que o sócio pode acender sozinho pelo app, no fim de semana.
     *
     * Sem contator não há o que acender: a flag marcada num espaço que perdeu o
     * switch não deve aparecer na lista do app.
     */
    public function scopeSelfServiceLighting($query)
    {
        return $query->where('self_service_lighting', true)->whereNotNull('contactor_id');
    }

    public function scheduleRules()
    {
        return $this->belongsToMany(ScheduleRules::class, 'place_schedule_rule', 'place_id', 'schedule_rule_id')
            ->withPivot('id')
            ->withTimestamps();
    }

}
