<?php

namespace App\Models\Placar;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Escalacao extends Model
{
    use HasFactory;

    protected $table = 'escalacoes';

    protected $fillable = [
        'jogo_id',
        'time_id',
        'jogador_id',
        'numero',
        'titular',
        'capitao',
    ];

    protected function casts(): array
    {
        return [
            'titular' => 'boolean',
            'capitao' => 'boolean',
        ];
    }

    public function jogo()
    {
        return $this->belongsTo(Jogo::class);
    }

    public function time()
    {
        return $this->belongsTo(Time::class);
    }

    public function jogador()
    {
        return $this->belongsTo(Jogador::class);
    }
}
