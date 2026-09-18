<?php

namespace App\Models\Replay;

use App\Models\Place;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Câmera/botão instalado numa quadra. Endereçada pelo `external_id` — o
 * identificador do equipamento no sistema de captura — em toda a API.
 */
class Camera extends Model
{
    use HasFactory;

    protected $table = 'replay_cameras';

    /** Campo de futebol tem uma câmera por metade; esse é o teto. */
    const MAX_PER_PLACE = 2;

    protected $fillable = [
        'place_id',
        'external_id',
        'name',
        'position',
        'active',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    public function place()
    {
        return $this->belongsTo(Place::class, 'place_id');
    }

    public function videos()
    {
        return $this->hasMany(Video::class, 'replay_camera_id');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Sem notícia há mais de uma hora é sinal de equipamento mudo — o
     * heartbeat do sistema de captura é de minutos.
     */
    public function isSilent(): bool
    {
        return $this->last_seen_at === null || $this->last_seen_at->lt(now()->subHour());
    }
}
