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

    /**
     * Aceita slug ('futsal') ou id (1) — é como a API recebe `modalidade`
     * nos endpoints de criação em campo e no filtro de jogos/times. Único
     * ponto de resolução, reaproveitado pelos controllers e pelos
     * validators que precisam conferir "o time é desta modalidade?".
     */
    public static function resolver(string|int|null $valor): ?self
    {
        if (blank($valor)) {
            return null;
        }

        return static::where('slug', $valor)->orWhere('id', $valor)->first();
    }
}
