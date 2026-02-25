<?php

namespace App\Models\Tournament;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Tournament\Category;
use App\Models\Status;

class Tournament extends Model
{
    protected $fillable = [
        'title', 'description', 'start_date', 'end_date', 
        'start_date_subscription', 'end_date_subscription', 
        'max_teams', 'status_id', 'group_id'
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'start_date_subscription' => 'datetime',
        'end_date_subscription' => 'datetime',
    ];

    // Relacionamento com as categorias (Tabela Pivot)
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'tournaments_categories')
                    ->withPivot('id', 'entry_price')
                    ->withTimestamps();
    }

    // Opcional: Relacionamentos com as tabelas de Status e PlaceGroup (se existirem os Models)
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }
}