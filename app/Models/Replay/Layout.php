<?php

namespace App\Models\Replay;

use App\Models\Place;
use App\Models\PlaceGroup;
use App\Services\Replay\MediaService;
use App\Support\Replay\Orientation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Layout de logomarcas de um esporte ou de uma quadra, para UMA orientação.
 *
 * O layout guarda as peças (LayoutItem) e o resultado da composição: o PNG
 * transparente que o sistema de captura queima sobre o vídeo e, quando há GIF
 * animado, o WebM com canal alpha. Renderizar é trabalho do OverlayRenderer —
 * aqui só ficam os caminhos e o hash.
 */
class Layout extends Model
{
    use HasFactory;

    protected $table = 'replay_layouts';

    protected $fillable = [
        'place_group_id',
        'place_id',
        'orientation',
        'name',
        'active',
        'overlay_path',
        'overlay_animated_path',
        'overlay_hash',
        'overlay_rendered_at',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'overlay_rendered_at' => 'datetime',
        ];
    }

    public function items()
    {
        return $this->hasMany(LayoutItem::class, 'replay_layout_id')->orderBy('z_index');
    }

    public function group()
    {
        return $this->belongsTo(PlaceGroup::class, 'place_group_id');
    }

    public function place()
    {
        return $this->belongsTo(Place::class, 'place_id');
    }

    public function overlayUrl(): ?string
    {
        return MediaService::url($this->overlay_path);
    }

    public function animatedOverlayUrl(): ?string
    {
        return MediaService::url($this->overlay_animated_path);
    }

    /** @return array{width: int, height: int} */
    public function dimensions(): array
    {
        return Orientation::dimensions($this->orientation);
    }

    public function ownerLabel(): string
    {
        return $this->place_id ? 'Quadra' : 'Esporte';
    }
}
