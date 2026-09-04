<?php

namespace App\Models\Placar;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Elenco extends Model
{
    use HasFactory;

    protected $table = 'elencos';

    protected $fillable = [
        'time_id',
        'jogador_id',
        'temporada',
        'numero',
        'posicao',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'temporada' => 'integer',
            'ativo' => 'boolean',
        ];
    }

    public function time()
    {
        return $this->belongsTo(Time::class);
    }

    public function jogador()
    {
        return $this->belongsTo(Jogador::class);
    }

    public function scopeDaTemporada($query, ?int $temporada)
    {
        return $query->where('temporada', $temporada ?? now()->year);
    }
}
