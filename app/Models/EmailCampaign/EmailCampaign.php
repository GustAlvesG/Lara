<?php

namespace App\Models\EmailCampaign;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Campanha do Disparo de E-mails (docs/funcionalidades/disparo-emails.md).
 *
 * Quem envia é o `email-campaigns:send` do scheduler, a cada minuto — nunca a
 * tela. Por isso o disparo segue com o navegador fechado: a tela só muda a
 * situação (`sending`, `paused`, `canceled`) e o comando obedece na rodada
 * seguinte.
 *
 *   draft ──► scheduled ──► sending ──► done
 *     ▲          │            │ ▲
 *     └──────────┘            ▼ │
 *                            paused        (sending/paused/scheduled ► canceled)
 */
class EmailCampaign extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_SENDING = 'sending';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_DONE = 'done';
    public const STATUS_CANCELED = 'canceled';

    /** Texto formatado no editor visual (O Lara monta o HTML) ou HTML à mão. */
    public const EDITOR_VISUAL = 'visual';
    public const EDITOR_HTML = 'html';

    /** @var array<string, string> */
    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Rascunho',
        self::STATUS_SCHEDULED => 'Agendada',
        self::STATUS_SENDING => 'Enviando',
        self::STATUS_PAUSED => 'Pausada',
        self::STATUS_DONE => 'Concluída',
        self::STATUS_CANCELED => 'Cancelada',
    ];

    /** Tom do selo (<x-pill kind>) de cada situação. */
    public const STATUS_PILLS = [
        self::STATUS_DRAFT => 'off',
        self::STATUS_SCHEDULED => 'info',
        self::STATUS_SENDING => 'warn',
        self::STATUS_PAUSED => 'warn',
        self::STATUS_DONE => 'ok',
        self::STATUS_CANCELED => 'danger',
    ];

    protected $fillable = [
        'name',
        'subject',
        'from_address',
        'from_name',
        'reply_to',
        'editor',
        'body',
        'html',
        'status',
        'scheduled_at',
        'started_at',
        'finished_at',
        'columns',
        'list_name',
        'last_error',
        'created_by',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'columns' => 'array',
    ];

    public function recipients(): HasMany
    {
        return $this->hasMany(EmailCampaignRecipient::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function usesVisualEditor(): bool
    {
        return $this->editor === self::EDITOR_VISUAL;
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function statusPill(): string
    {
        return self::STATUS_PILLS[$this->status] ?? 'off';
    }

    /**
     * Texto, remetente e lista só mudam antes de começar ou com o envio
     * pausado — no meio do envio, metade receberia uma versão e metade outra.
     */
    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_PAUSED], true);
    }

    public function canStart(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function canPause(): bool
    {
        return $this->status === self::STATUS_SENDING;
    }

    public function canResume(): bool
    {
        return $this->status === self::STATUS_PAUSED;
    }

    public function canCancel(): bool
    {
        return in_array($this->status, [self::STATUS_SCHEDULED, self::STATUS_SENDING, self::STATUS_PAUSED], true);
    }

    /** Agendada volta a rascunho: dá para mexer e agendar de novo. */
    public function canUnschedule(): bool
    {
        return $this->status === self::STATUS_SCHEDULED;
    }

    /** Excluir apaga a lista e o histórico; durante o envio, não. */
    public function canDelete(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_DONE, self::STATUS_CANCELED], true);
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_SCHEDULED, self::STATUS_SENDING], true);
    }

    /**
     * Contagem por situação dos destinatários, numa consulta só.
     *
     * @return array{total: int, pending: int, sent: int, failed: int, skipped: int}
     */
    public function counts(): array
    {
        $byStatus = $this->recipients()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $counts = ['pending' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0];

        foreach ($byStatus as $status => $total) {
            $counts[$status] = (int) $total;
        }

        return ['total' => array_sum($counts)] + $counts;
    }

    /**
     * Variáveis que o texto pode usar: as fixas e as colunas da lista.
     *
     * @return array<string, string> slug => rótulo
     */
    public function variables(): array
    {
        return ['email' => 'E-mail', 'nome' => 'Nome', 'primeiro_nome' => 'Primeiro nome']
            + array_diff_key($this->columns ?? [], array_flip(['email', 'nome']));
    }
}
