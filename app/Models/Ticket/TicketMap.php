<?php

namespace App\Models\Ticket;

use App\Support\ImageSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Mapa de lugares do evento ("Mesas", "Bistrôs", "Camarotes"). Os lugares
 * (TicketSeat) e as áreas de orientação ficam numa área de desenho de
 * `width` × `height` unidades, por cima da imagem de fundo (opcional).
 *
 * Áreas (`areas`, JSON): só desenho, para orientar quem escolhe (palco,
 * piscina, banheiros) — não são clicáveis nem vendidas. Cada uma:
 * `{label, shape: rect|ellipse, x, y, w, h, color}` (x/y = canto superior
 * esquerdo, nas unidades do mapa; color = uma de AREA_COLORS).
 *
 * O tipo de ingresso de lugar marcado aponta para o mapa (TicketType
 * `ticket_map_id`, e a categoria, se só parte dos lugares). Vários tipos podem
 * usar o mesmo mapa: "Mesa Sábado" e "Mesa Domingo", ou "Mesa Vip" (categoria
 * Vip) e "Mesa" (categoria Comum).
 */
class TicketMap extends Model
{
    /** @var array<string, string> */
    public const AREA_SHAPES = ['rect' => 'Retângulo', 'ellipse' => 'Elipse'];

    /** @var array<string, string> nome => rótulo */
    public const AREA_COLORS = [
        'stage' => 'Palco (grená)',
        'water' => 'Água (azul)',
        'green' => 'Verde',
        'service' => 'Serviço (cinza)',
        'warn' => 'Atenção (amarelo)',
    ];

    public const MIN_SIZE = 200;
    public const MAX_SIZE = 4000;

    protected $fillable = [
        'ticket_event_id',
        'name',
        'background',
        'width',
        'height',
        'areas',
        'sort_order',
    ];

    protected $casts = [
        'ticket_event_id' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'areas' => 'array',
        'sort_order' => 'integer',
    ];

    protected $attributes = [
        'width' => 1000,
        'height' => 600,
        'sort_order' => 0,
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(TicketEvent::class, 'ticket_event_id');
    }

    public function seats(): HasMany
    {
        return $this->hasMany(TicketSeat::class)->orderBy('id');
    }

    public function types(): HasMany
    {
        return $this->hasMany(TicketType::class);
    }

    public function backgroundUrl(): ?string
    {
        return ImageSource::url($this->background);
    }

    /** As categorias dos lugares, para o cadastro do tipo. @return list<string> */
    public function categories(): array
    {
        return $this->seats->pluck('category')->filter()->unique()->sort()->values()->all();
    }
}
