<?php

namespace App\Models\Social;

use App\Models\Place;
use Illuminate\Database\Eloquent\Model;

/**
 * Foto da galeria de um espaço social (o carrossel do site).
 *
 * `file` e `thumb` são caminhos relativos a `public/images` — arquivo
 * estático, servido pelo servidor web sem passar pelo Lara. Quem grava e
 * apaga é o `PlacePhotoService`.
 */
class PlacePhoto extends Model
{
    /** Pasta dentro de `public/images`. */
    public const DIR = 'galeria';

    /** Máximo de fotos por espaço (ativas ou não). */
    public const MAX_PER_PLACE = 20;

    protected $fillable = [
        'place_id',
        'file',
        'thumb',
        'width',
        'height',
        'caption',
        'position',
        'active',
        'created_by',
    ];

    protected $casts = [
        'width' => 'integer',
        'height' => 'integer',
        'position' => 'integer',
        'active' => 'boolean',
    ];

    public function place()
    {
        return $this->belongsTo(Place::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('position')->orderBy('id');
    }

    /** Caminho público, a partir da raiz do Lara: `images/galeria/12/x.jpg`. */
    public function publicPath(): string
    {
        return 'images/' . $this->file;
    }

    public function thumbPublicPath(): string
    {
        return 'images/' . $this->thumb;
    }

    /** URL do arquivo no Lara — para o painel e para o servidor do site buscar. */
    public function url(): string
    {
        return asset($this->publicPath());
    }

    public function thumbUrl(): string
    {
        return asset($this->thumbPublicPath());
    }

    /**
     * Formato da API do site. O navegador de quem visita o site NÃO alcança o
     * Lara: `path`/`thumb_path` são para o servidor do Next.js montar o
     * endereço com a base dele (LARA_URL) e servir o arquivo no próprio
     * domínio; `url`/`thumb_url` são o mesmo arquivo com o host por onde a
     * chamada chegou ao Lara. O nome do arquivo nunca muda (foto nova = nome
     * novo), então o site pode guardar em cache para sempre.
     */
    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'path' => $this->publicPath(),
            'thumb_path' => $this->thumbPublicPath(),
            'url' => $this->url(),
            'thumb_url' => $this->thumbUrl(),
            'width' => $this->width,
            'height' => $this->height,
            'caption' => $this->caption,
        ];
    }
}
