<?php

namespace App\Models\Ticket;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Lote de um tipo de ingresso: quantidade, janela de venda e os dois preços.
 *
 * O lote fecha pela data ou quando esgota, o que vier primeiro; o seguinte
 * (`sequence`) abre na hora, se a janela dele já começou. Datas nulas = a
 * janela de vendas do evento.
 *
 * `price_non_member` nulo = este lote não é vendido a não sócio. `quantity`
 * nulo = sem limite de ingressos (não esgota; fecha pela data ou pelo "Virar
 * lote agora").
 */
class TicketLot extends Model
{
    public const PRICE_MEMBER = 'member';
    public const PRICE_NON_MEMBER = 'non_member';

    /** @var array<string, string> */
    public const PRICE_LABELS = [
        self::PRICE_MEMBER => 'Sócio',
        self::PRICE_NON_MEMBER => 'Não sócio',
    ];

    protected $fillable = [
        'ticket_type_id',
        'name',
        'sequence',
        'quantity',
        'starts_at',
        'ends_at',
        'price_member',
        'price_non_member',
        'active',
    ];

    protected $casts = [
        'ticket_type_id' => 'integer',
        'sequence' => 'integer',
        'quantity' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'price_member' => 'decimal:2',
        'price_non_member' => 'decimal:2',
        'active' => 'boolean',
    ];

    protected $attributes = [
        'active' => true,
        'sequence' => 1,
    ];

    public function type(): BelongsTo
    {
        return $this->belongsTo(TicketType::class, 'ticket_type_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /** O preço para sócio ou não sócio; null = não vendido a esse público. */
    public function priceFor(string $kind): ?float
    {
        $price = $kind === self::PRICE_MEMBER ? $this->price_member : $this->price_non_member;

        return $price === null ? null : (float) $price;
    }

    /** A janela do lote já começou e ainda não acabou (sem olhar estoque). */
    public function withinDates(Carbon $at): bool
    {
        return ($this->starts_at === null || $at->greaterThanOrEqualTo($this->starts_at))
            && ($this->ends_at === null || $at->lessThanOrEqualTo($this->ends_at));
    }
}
