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
}
