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
 * Mesa cativa (`captive_title`): só quem é daquela matrícula compra o lugar,
 * até o evento liberar as cativas (`TicketEvent::captivesReleased`); depois,
 * é um lugar comum. Ver `captiveFor()`.
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
        'captive_title',
        'captive_name',
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

    /** A matrícula para quem o lugar está guardado agora (null = livre para todos). */
    public function captiveFor(TicketEvent $event, ?\Illuminate\Support\Carbon $at = null): ?string
    {
        return $this->captive_title !== null && ! $event->captivesReleased($at) ? $this->captive_title : null;
    }

    /** A mesma matrícula, sem zeros à esquerda nem espaços ("0123" = "123"). */
    public static function sameTitle(?string $a, ?string $b): bool
    {
        $norm = fn (?string $t) => ltrim(trim((string) $t), '0');

        return filled($a) && filled($b) && $norm($a) === $norm($b);
    }

    /** "Mesa 12 (4 lugares)". */
    public function fullLabel(): string
    {
        return $this->label . ($this->places ? " ({$this->places} lugar" . ($this->places > 1 ? 'es' : '') . ')' : '');
    }
}
