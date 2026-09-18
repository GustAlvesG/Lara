<?php

namespace App\Models\Replay;

use App\Models\Place;
use App\Models\PlaceGroup;
use App\Support\Replay\Orientation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Configuração de vídeo (orientação + duração do clipe) de um esporte ou de
 * uma quadra. Quem decide qual das duas vale para uma câmera é o
 * ReplayResolver — este model não sabe nada de herança.
 */
class Setting extends Model
{
    use HasFactory;

    protected $table = 'replay_settings';

    protected $fillable = [
        'place_group_id',
        'place_id',
        'orientation',
        'clip_seconds',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'clip_seconds' => 'integer',
        ];
    }

    public function group()
    {
        return $this->belongsTo(PlaceGroup::class, 'place_group_id');
    }

    public function place()
    {
        return $this->belongsTo(Place::class, 'place_id');
    }

    /** Rótulo do dono, para a tela dizer de onde a configuração veio. */
    public function ownerLabel(): string
    {
        return $this->place_id ? 'Quadra' : 'Esporte';
    }

    public function orientationLabel(): string
    {
        return Orientation::label($this->orientation);
    }
}
