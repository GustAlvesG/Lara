<?php

namespace App\Models\Ticket;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Um ingresso: uma pessoa (nome e CPF) num evento, com tipo, lote e preço
 * congelados no pedido.
 *
 *   `pending` (pedido aguardando pagamento) → `valid` (pago) → `used` (entrou)
 *   saídas: `expired` (pedido expirou ou houve desistência), `cancelled`
 *   (a equipe cancelou, com ou sem estorno)
 *
 * Uma pessoa tem no máximo um ingresso ativo (pendente dentro do prazo,
 * válido ou usado) por dia do evento: no evento sem dias, um no evento; no de
 * vários dias, uma diária por dia ou o passaporte. `code` é o que vai no QR e
 * o que a portaria digita; formato `ING-XXXXXXXX` para o Monitor de Acesso
 * reconhecer.
 *
 * `used` = já entrou ao menos uma vez; `checked_in_at` é a última entrada. As
 * entradas ficam em `checkins` (o passaporte tem uma por dia).
 *
 * Ingresso de lugar marcado (`ticket_seat_id`, tipo com mapa): a mesa, o
 * bistrô ou o camarote, no nome do responsável. Não é entrada — fica fora do
 * "um por CPF", da lotação e da portaria (`scopePeople` separa as entradas).
 */
class Ticket extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_VALID = 'valid';
    public const STATUS_USED = 'used';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    /** @var array<string, string> */
    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'Aguardando pagamento',
        self::STATUS_VALID => 'Válido',
        self::STATUS_USED => 'Utilizado',
        self::STATUS_EXPIRED => 'Expirado',
        self::STATUS_CANCELLED => 'Cancelado',
    ];

    /** Pagos: contam no estoque de vez. */
    public const SOLD = [self::STATUS_VALID, self::STATUS_USED];

    public const CHECKIN_LARA = 'lara';
    public const CHECKIN_MONITOR = 'monitor';

    public const CODE_PREFIX = 'ING-';
    public const CODE_PATTERN = '/^ING-[A-Z2-9]{8}$/';

    /** Sem 0/O, 1/I/L: o código também é digitado na portaria. */
    private const CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    protected $fillable = [
        'ticket_order_id',
        'ticket_event_id',
        'ticket_type_id',
        'ticket_lot_id',
        'ticket_seat_id',
        'price_kind',
        'list_price',
        'discount_amount',
        'amount',
        'holder_name',
        'holder_cpf',
        'code',
        'status',
        'refunded_amount',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
    ];

    protected $casts = [
        'ticket_order_id' => 'integer',
        'ticket_event_id' => 'integer',
        'ticket_type_id' => 'integer',
        'ticket_lot_id' => 'integer',
        'ticket_seat_id' => 'integer',
        'list_price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'amount' => 'decimal:2',
        'refunded_amount' => 'decimal:2',
        'checked_in_at' => 'datetime',
        'print_count' => 'integer',
        'printed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
        'refunded_amount' => 0,
        'discount_amount' => 0,
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(TicketOrder::class, 'ticket_order_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(TicketEvent::class, 'ticket_event_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(TicketType::class, 'ticket_type_id');
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(TicketLot::class, 'ticket_lot_id');
    }

    public function seat(): BelongsTo
    {
        return $this->belongsTo(TicketSeat::class, 'ticket_seat_id');
    }

    /** Ingresso de lugar marcado (mesa, bistrô, camarote), não de entrada? */
    public function isSeat(): bool
    {
        return $this->ticket_seat_id !== null;
    }

    /** O tipo com a validade e, se for de lugar, o lugar: "Mesa · Sáb 13/02 · M12". */
    public function typeLabel(): string
    {
        $name = (string) $this->type?->fullName();

        return $this->seat ? trim($name . ' · ' . $this->seat->label, ' ·') : $name;
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    public function checkins(): HasMany
    {
        return $this->hasMany(TicketCheckin::class)->orderBy('checked_in_at');
    }

    /**
     * Ocupam lugar: pagos, ou pendentes de pedido ainda dentro do prazo. O
     * pendente vencido não segura, mesmo antes de o comando de expiração
     * passar por ele.
     */
    public function scopeOccupying(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereIn('tickets.status', self::SOLD)
                ->orWhere(fn (Builder $p) => $p->where('tickets.status', self::STATUS_PENDING)
                    ->whereHas('order', fn (Builder $o) => $o->where('status', TicketOrder::STATUS_PENDING_PAYMENT)->where('hold_until', '>', now())));
        });
    }

    /** Só os ingressos de entrada (uma pessoa), sem os de lugar marcado. */
    public function scopePeople(Builder $query): Builder
    {
        return $query->whereNull('tickets.ticket_seat_id');
    }

    public static function newCode(): string
    {
        do {
            $code = self::CODE_PREFIX;
            for ($i = 0; $i < 8; $i++) {
                $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
            }
        } while (self::where('code', $code)->exists());

        return $code;
    }

    /** O que a portaria digitou, no formato gravado (`ing-ab12cd34` → `ING-AB12CD34`). */
    public static function normalizeCode(?string $typed): string
    {
        $clean = Str::upper(preg_replace('/[^A-Za-z0-9]/', '', (string) $typed));

        if (str_starts_with($clean, 'ING')) {
            $clean = substr($clean, 3);
        }

        return self::CODE_PREFIX . $clean;
    }
}
