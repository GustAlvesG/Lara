<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\PlaceGroup;
use App\Models\Schedule;
use App\Models\Contactor;
use Illuminate\Support\Facades\DB;

class Place extends Model
{
    use HasFactory;
    use SoftDeletes;

    
    protected $fillable = [
        'name',
        'description',
        'amenities',
        'contactor_id',
        'self_service_lighting',
        'image',
        'place_group_id',
        'price',
        'capacity',
        'max_guests',
        'extra_guests',
        'extra_guest_price',
        'status_id',
    ];

    protected $casts = [
        'self_service_lighting' => 'boolean',
        'capacity' => 'integer',
        'max_guests' => 'integer',
        'extra_guests' => 'integer',
        'extra_guest_price' => 'decimal:2',
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

    /**
     * Espaços que não podem ser usados no mesmo dia que este (ver a migration
     * de `place_conflicts`). Só os que este espaço declarou; para a regra em
     * si, que vale nos dois sentidos, use conflictingPlaceIds().
     */
    public function conflicts()
    {
        return $this->belongsToMany(Place::class, 'place_conflicts', 'place_id', 'conflicting_place_id')
            ->withTimestamps();
    }

    /**
     * Ids dos espaços que uma reserva neste ocupa junto — e que, ocupados,
     * ocupam este. O par vale nos dois sentidos: o salão declara a quadra, e
     * a reserva da quadra também bloqueia o salão.
     *
     * @return array<int, int>
     */
    public static function conflictingPlaceIds(int $placeId): array
    {
        return DB::table('place_conflicts')
            ->where('place_id', $placeId)
            ->pluck('conflicting_place_id')
            ->merge(DB::table('place_conflicts')->where('conflicting_place_id', $placeId)->pluck('place_id'))
            ->map(fn ($id) => (int) $id)
            ->reject(fn ($id) => $id === $placeId)
            ->unique()
            ->values()
            ->all();
    }

    public function isSocial(): bool
    {
        return (bool) $this->group?->isSocial();
    }

    public function scheduleRules()
    {
        return $this->belongsToMany(ScheduleRules::class, 'place_schedule_rule', 'place_id', 'schedule_rule_id')
            ->withPivot('id')
            ->withTimestamps();
    }

}
