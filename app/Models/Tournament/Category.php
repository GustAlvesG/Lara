<?php

namespace App\Models\Tournament;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Category extends Model
{
    protected $fillable = ['name', 'member_by_team'];

    public function tournaments(): BelongsToMany
    {
        return $this->belongsToMany(Tournament::class, 'tournaments_categories')
                    ->withPivot('id', 'entry_price')
                    ->withTimestamps();
    }
}