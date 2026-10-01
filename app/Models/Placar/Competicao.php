<?php

namespace App\Models\Placar;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Competicao extends Model
{
    use HasFactory;

    protected $table = 'competicoes';

    protected $fillable = [
        'nome',
        'modalidade_id',
        'temporada',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'temporada' => 'integer',
            'ativo' => 'boolean',
        ];
    }

    public function modalidade()
    {
        return $this->belongsTo(Modalidade::class);
    }

    public function jogos()
    {
        return $this->hasMany(Jogo::class);
    }

    public function scopeAtivas($query)
    {
        return $query->where('ativo', true);
    }

    /** Busca da listagem: nome, temporada ou modalidade. */
    public function scopeBusca($query, ?string $termo)
    {
        $termo = trim((string) $termo);
        if ($termo === '') {
            return $query;
        }

        return $query->where(function ($q) use ($termo) {
            $q->where('nome', 'like', "%{$termo}%")
              ->orWhere('temporada', 'like', "%{$termo}%")
              ->orWhereHas('modalidade', fn ($m) => $m->where('nome', 'like', "%{$termo}%"));
        });
    }
}
