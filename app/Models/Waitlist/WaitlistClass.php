<?php

namespace App\Models\Waitlist;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Turma da fila de espera (InfoClube › Fila de Espera): modalidade + nome da
 * turma, cadastrados à mão no Lara. Não tem vínculo com as turmas do
 * MultiClubes — a fila daqui é só a dos não sócios.
 *
 * Turma inativa não recebe gente nova, mas a fila dela continua visível.
 */
class WaitlistClass extends Model
{
    protected $table = 'waitlist_classes';

    protected $fillable = ['modality', 'name', 'notes', 'active', 'created_by'];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function entries(): HasMany
    {
        return $this->hasMany(WaitlistEntry::class);
    }

    public function waitingEntries(): HasMany
    {
        return $this->entries()->waiting();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('modality')->orderBy('name');
    }

    /** "Natação — Infantil ter/qui 9h", para listas e mensagens. */
    public function label(): string
    {
        return "{$this->modality} — {$this->name}";
    }
}
