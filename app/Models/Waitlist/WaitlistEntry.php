<?php

namespace App\Models\Waitlist;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Uma pessoa na fila de uma turma. A ordem é a data de entrada (`joined_at`,
 * desempate pelo id) e não muda por ligação sem resposta: só a secretaria
 * tira alguém da fila (matriculado, desistiu ou removido). Reativar devolve a
 * pessoa à posição original, porque a data de entrada é a mesma.
 */
class WaitlistEntry extends Model
{
    public const STATUS_WAITING = 'waiting';
    public const STATUS_ENROLLED = 'enrolled';
    public const STATUS_WITHDRAWN = 'withdrawn';
    public const STATUS_REMOVED = 'removed';

    /** situação => [rótulo, tipo do <x-pill>] */
    public const STATUSES = [
        self::STATUS_WAITING => ['Aguardando', 'warn'],
        self::STATUS_ENROLLED => ['Matriculado', 'ok'],
        self::STATUS_WITHDRAWN => ['Desistiu', 'off'],
        self::STATUS_REMOVED => ['Removido', 'danger'],
    ];

    /** Como a pessoa pode sair da fila. */
    public const CLOSING_STATUSES = [self::STATUS_ENROLLED, self::STATUS_WITHDRAWN, self::STATUS_REMOVED];

    protected $table = 'waitlist_entries';

    protected $fillable = [
        'waitlist_class_id', 'waitlist_person_id', 'joined_at', 'status',
        'closed_at', 'closed_by', 'close_reason', 'created_by',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function waitlistClass(): BelongsTo
    {
        return $this->belongsTo(WaitlistClass::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(WaitlistPerson::class, 'waitlist_person_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(WaitlistContact::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    public function latestContact(): HasOne
    {
        return $this->hasOne(WaitlistContact::class)->latestOfMany();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function scopeWaiting(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_WAITING);
    }

    public function scopeClosed(Builder $query): Builder
    {
        return $query->where('status', '!=', self::STATUS_WAITING);
    }

    /** A ordem da fila: quem entrou antes; no mesmo instante, quem foi gravado antes. */
    public function scopeInQueueOrder(Builder $query): Builder
    {
        return $query->orderBy('joined_at')->orderBy('id');
    }

    public function isWaiting(): bool
    {
        return $this->status === self::STATUS_WAITING;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status][0] ?? $this->status;
    }

    public function statusKind(): string
    {
        return self::STATUSES[$this->status][1] ?? 'off';
    }

    /**
     * Posição na fila da turma (1 = a próxima a ser chamada), ou null quando
     * a pessoa já saiu da fila.
     */
    public function position(): ?int
    {
        if (!$this->isWaiting()) {
            return null;
        }

        return 1 + static::query()
            ->waiting()
            ->where('waitlist_class_id', $this->waitlist_class_id)
            ->where(function (Builder $q) {
                $q->where('joined_at', '<', $this->joined_at)
                    ->orWhere(fn (Builder $same) => $same->where('joined_at', $this->joined_at)->where('id', '<', $this->id));
            })
            ->count();
    }

    /** Dias completos na fila (até hoje, ou até sair). */
    public function daysWaiting(): int
    {
        return (int) $this->joined_at->copy()->startOfDay()
            ->diffInDays(($this->closed_at ?? now())->copy()->startOfDay());
    }
}
