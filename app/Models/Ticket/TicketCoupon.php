<?php

namespace App\Models\Ticket;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Cupom de desconto de um evento. Abate de CADA ingresso a que se aplica:
 * porcentagem do preço do lote (`percent`) ou valor fixo (`fixed`, nunca
 * abaixo de zero).
 *
 * Aplica-se aos tipos de `ticket_type_ids` (nulo = todos) e ao público de
 * `audience` (`all`, `member`, `non_member`). `max_uses` conta ingressos com
 * desconto que ocupam lugar (pagos ou pendentes no prazo); nulo = sem limite.
 * O código é guardado em maiúsculas e conferido sem diferença de caixa.
 */
class TicketCoupon extends Model
{
    public const KIND_PERCENT = 'percent';
    public const KIND_FIXED = 'fixed';

    /** @var array<string, string> */
    public const KIND_LABELS = [
        self::KIND_PERCENT => 'Porcentagem',
        self::KIND_FIXED => 'Valor fixo',
    ];

    public const AUDIENCE_ALL = 'all';

    /** @var array<string, string> */
    public const AUDIENCE_LABELS = [
        self::AUDIENCE_ALL => 'Sócios e não sócios',
        TicketLot::PRICE_MEMBER => 'Só sócios',
        TicketLot::PRICE_NON_MEMBER => 'Só não sócios',
    ];

    protected $fillable = [
        'ticket_event_id',
        'code',
        'description',
        'kind',
        'value',
        'audience',
        'ticket_type_ids',
        'max_uses',
        'starts_at',
        'ends_at',
        'active',
        'created_by',
    ];

    protected $casts = [
        'ticket_event_id' => 'integer',
        'value' => 'decimal:2',
        'ticket_type_ids' => 'array',
        'max_uses' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'active' => 'boolean',
    ];

    protected $attributes = [
        'audience' => self::AUDIENCE_ALL,
        'active' => true,
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(TicketEvent::class, 'ticket_event_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(TicketOrder::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function setCodeAttribute(?string $value): void
    {
        $this->attributes['code'] = self::normalizeCode($value);
    }

    public static function normalizeCode(?string $value): string
    {
        return Str::upper(preg_replace('/\s+/', '', (string) $value));
    }

    public function withinDates(?Carbon $at = null): bool
    {
        $at ??= now();

        return ($this->starts_at === null || $at->greaterThanOrEqualTo($this->starts_at))
            && ($this->ends_at === null || $at->lessThanOrEqualTo($this->ends_at));
    }

    /** Vale para este tipo e este público (sem olhar datas nem usos)? */
    public function appliesTo(int $typeId, string $priceKind): bool
    {
        $types = $this->ticket_type_ids;

        return ($types === null || $types === [] || in_array($typeId, array_map('intval', $types), true))
            && ($this->audience === self::AUDIENCE_ALL || $this->audience === $priceKind);
    }

    /** Quanto abate de um ingresso com este preço. */
    public function discountFor(float $price): float
    {
        $discount = $this->kind === self::KIND_PERCENT
            ? round($price * min(100, (float) $this->value) / 100, 2)
            : (float) $this->value;

        return round(max(0, min($price, $discount)), 2);
    }

    /** Ingressos com este cupom que ocupam lugar (pagos ou pendentes no prazo). */
    public function usedCount(?int $exceptOrderId = null): int
    {
        return Ticket::occupying()
            ->where('discount_amount', '>', 0)
            ->whereHas('order', fn ($q) => $q->where('ticket_coupon_id', $this->id)
                ->when($exceptOrderId, fn ($o) => $o->where('id', '!=', $exceptOrderId)))
            ->count();
    }

    /** "10%" ou "R$ 20,00". */
    public function valueLabel(): string
    {
        return $this->kind === self::KIND_PERCENT
            ? rtrim(rtrim(number_format((float) $this->value, 2, ',', ''), '0'), ',') . '%'
            : 'R$ ' . number_format((float) $this->value, 2, ',', '.');
    }
}
