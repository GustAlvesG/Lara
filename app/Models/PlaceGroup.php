<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Place;
use App\Models\ScheduleRules;

class PlaceGroup extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name', 
        'category', 
        'image_vertical', 
        'image_horizontal', 
        'status'
    ];

    public function places()
    {
        return $this->hasMany(Place::class );
    }
}
