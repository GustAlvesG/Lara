<?php

namespace App\Models\Placar;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Modalidade extends Model
{
    use HasFactory;

    protected $table = 'modalidades';

    /** Slugs — é o que o Node usa no campo `esporte` do gameState. */
    const FUTSAL = 'futsal';
    const BASQUETE = 'basquete';
    const VOLEI = 'volei';

    protected $fillable = [
        'nome',
        'slug',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }

    public function times()
    {
        return $this->hasMany(Time::class);
    }

    public function competicoes()
    {
        return $this->hasMany(Competicao::class);
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
