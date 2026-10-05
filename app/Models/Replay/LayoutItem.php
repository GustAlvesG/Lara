<?php

namespace App\Models\Replay;

use App\Services\Replay\MediaService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Uma logomarca posicionada dentro de um layout. Coordenadas e tamanho em
 * percentual do frame — ver a migration para o porquê.
 */
class LayoutItem extends Model
{
    use HasFactory;

    protected $table = 'replay_layout_items';

    protected $fillable = [
        'replay_layout_id',
        'image_path',
        'animated',
        'x',
        'y',
        'width',
        'height',
        'opacity',
        'z_index',
    ];

    protected function casts(): array
    {
        return [
            'animated' => 'boolean',
            'x' => 'float',
            'y' => 'float',
            'width' => 'float',
            'height' => 'float',
            'opacity' => 'integer',
            'z_index' => 'integer',
        ];
    }

    public function layout()
    {
        return $this->belongsTo(Layout::class, 'replay_layout_id');
    }

    public function imageUrl(): ?string
    {
        return MediaService::url($this->image_path);
    }
}
