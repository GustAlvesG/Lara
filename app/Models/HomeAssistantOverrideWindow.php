<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HomeAssistantOverrideWindow extends Model
{
    protected $fillable = [
        'home_assistant_override_id',
        'turn_on_at',
        'turn_off_at',
        'state',
    ];

    public function override()
    {
        return $this->belongsTo(HomeAssistantOverride::class, 'home_assistant_override_id');
    }
}
