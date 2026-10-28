<?php

namespace App\Models\Ticket;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Um lugar do mapa: mesa, bistrô, camarote. Vendido como um ingresso do tipo
 * que aponta para o mapa (e para a categoria do lugar, se o tipo tiver uma),
 * no nome de quem é o responsável. Não é entrada: cada pessoa compra a dela.
 *
 * Um lugar por dia: a garantia é o índice único de `ticket_seat_claims`
 * (lugar, dia). `x`/`y` são o centro; `w`/`h` o tamanho, nas unidades do
 * mapa. `places` (quantas pessoas cabem) é só informação.
 *
 * Lugar que já teve ingresso não é apagado nem muda de rótulo ou categoria
 * (o rótulo está nos ingressos impressos): é desativado.
 */
class TicketSeat extends Model
{
    public const SHAPE_CIRCLE = 'circle';
    public const SHAPE_SQUARE = 'square';
    public const SHAPE_RECT = 'rect';

    /** @var array<string, string> */
    public const SHAPES = [
        self::SHAPE_CIRCLE => 'Redonda',
        self::SHAPE_SQUARE => 'Quadrada',
        self::SHAPE_RECT => 'Retangular',
    ];

    protected $fillable = [
        'ticket_map_id',
        'label',
        'category',
        'shape',
        'x',
        'y',
        'w',
        'h',
        'places',
        'active',
    ];

    protected $casts = [
        'ticket_map_id' => 'integer',
        'x' => 'integer',
        'y' => 'integer',
        'w' => 'integer',
        'h' => 'integer',
        'places' => 'integer',
        'active' => 'boolean',
    ];

    protected $attributes = [
        'shape' => self::SHAPE_CIRCLE,
        'w' => 40,
        'h' => 40,
        'active' => true,
    ];

    public function map(): BelongsTo
    {
        return $this->belongsTo(TicketMap::class, 'ticket_map_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function claims(): HasMany
    {
        return $this->hasMany(TicketSeatClaim::class);
    }

    /** O tipo vende este lugar? Mesmo mapa e, se o tipo tem categoria, a mesma. */
    public function soldBy(TicketType $type): bool
    {
        return $type->ticket_map_id === $this->ticket_map_id
            && ($type->seat_category === null || $type->seat_category === $this->category);
    }

    /** "Mesa 12 (4 lugares)". */
    public function fullLabel(): string
    {
        return $this->label . ($this->places ? " ({$this->places} lugar" . ($this->places > 1 ? 'es' : '') . ')' : '');
    }
}
