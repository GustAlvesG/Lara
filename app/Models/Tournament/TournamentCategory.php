<?php

namespace App\Models\Tournament;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TournamentCategory extends Model
{
    // Informa o nome exato da tabela
    protected $table = 'tournaments_categories';

    protected $fillable = ['tournament_id', 'category_id', 'entry_price'];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(TournamentSubscription::class, 'tournament_category_id');
    }
}