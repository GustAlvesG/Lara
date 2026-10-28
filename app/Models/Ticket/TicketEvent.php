<?php

namespace App\Models\Ticket;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Evento com venda de ingressos pelo site (docs/funcionalidades/ingressos.md).
 *
 * "Vendas abertas" não é situação gravada: sai das datas (`salesOpen()`). O
 * site só cria pedido dentro dessa janela; o balcão do Lara vende fora dela
 * com aviso.
 *
 * `capacity` é a lotação somando todos os tipos (nulo = sem limite); cada lote
 * tem a sua quantidade. No evento de vários dias (`days`), a lotação vale para
 * cada dia: o dia conta as diárias dele mais os passaportes.
 *
 * Evento de vários dias: cadastre os dias (TicketEventDay). Cada tipo é uma
 * diária (preso a um dia) ou, sem dia, o passaporte de todos os dias — o
 * passaporte só existe se a equipe cadastrar um tipo sem dia.
 *
 * Lugar marcado: os mapas (TicketMap) têm os lugares; o tipo que aponta para
 * um mapa vende os lugares dele (TicketSeatService).
 */
class TicketEvent extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_FINISHED = 'finished';
    public const STATUS_CANCELLED = 'cancelled';

    /** @var array<string, string> */
    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Rascunho',
        self::STATUS_PUBLISHED => 'Publicado',
        self::STATUS_FINISHED => 'Encerrado',
        self::STATUS_CANCELLED => 'Cancelado',
    ];

    /** O que o site mostra. */
    public const VISIBLE = [self::STATUS_PUBLISHED, self::STATUS_FINISHED];

    protected $fillable = [
        'title',
        'description',
        'rules',
        'image',
        'venue',
        'starts_at',
        'ends_at',
        'sales_starts_at',
        'sales_ends_at',
        'capacity',
        'max_per_order',
        'status',
        'created_by',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'sales_starts_at' => 'datetime',
        'sales_ends_at' => 'datetime',
        'capacity' => 'integer',
        'max_per_order' => 'integer',
    ];

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
        'max_per_order' => 10,
    ];

    public function types(): HasMany
    {
        return $this->hasMany(TicketType::class)->orderBy('sort_order')->orderBy('id');
    }

    public function days(): HasMany
    {
        return $this->hasMany(TicketEventDay::class)->orderBy('starts_at')->orderBy('id');
    }

    /** Evento de vários dias (com diárias e, se houver, passaporte)? */
    public function hasDays(): bool
    {
        return $this->days->isNotEmpty();
    }

    /** O dia com a entrada aberta em `$at` (o primeiro, se dois se cruzarem). */
    public function dayAt(?Carbon $at = null): ?TicketEventDay
    {
        return $this->days->first(fn (TicketEventDay $day) => $day->checkinOpen($at));
    }

    /** Mapas de lugar marcado (mesas, bistrôs, camarotes). */
    public function maps(): HasMany
    {
        return $this->hasMany(TicketMap::class)->orderBy('sort_order')->orderBy('id');
    }

    public function coupons(): HasMany
    {
        return $this->hasMany(TicketCoupon::class)->orderBy('code');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(TicketOrder::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function salesOpen(?Carbon $at = null): bool
    {
        $at ??= now();

        return $this->status === self::STATUS_PUBLISHED
            && $at->betweenIncluded($this->sales_starts_at, $this->sales_ends_at)
            && $at->lessThan($this->ends_at);
    }

    /** A entrada abre `tickets.checkin_hours_before` antes do início e fecha no fim. */
    public function checkinOpensAt(): Carbon
    {
        return $this->starts_at->copy()->subHours(max(0, (int) config('tickets.checkin_hours_before', 3)));
    }

    public function checkinOpen(?Carbon $at = null): bool
    {
        $at ??= now();

        return $this->status !== self::STATUS_CANCELLED
            && $this->status !== self::STATUS_DRAFT
            && $at->betweenIncluded($this->checkinOpensAt(), $this->ends_at);
    }

    /** Eventos com a entrada aberta agora (ou começando nas próximas horas). */
    public function scopeCheckinWindow($query, ?Carbon $at = null)
    {
        $at ??= now();

        return $query->whereIn('status', [self::STATUS_PUBLISHED, self::STATUS_FINISHED])
            ->where('starts_at', '<=', $at->copy()->addHours(max(0, (int) config('tickets.checkin_hours_before', 3))))
            ->where('ends_at', '>=', $at);
    }

    /** "12/11/2026 20:00" ou "12/11 20:00 a 13/11 02:00". */
    public function datesLabel(): string
    {
        if ($this->starts_at->isSameDay($this->ends_at)) {
            return $this->starts_at->format('d/m/Y H:i') . ' às ' . $this->ends_at->format('H:i');
        }

        return $this->starts_at->format('d/m/Y H:i') . ' a ' . $this->ends_at->format('d/m/Y H:i');
    }
}
