<?php

namespace App\Models\EmailCampaign;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Um destinatário da campanha. `data` guarda a linha da planilha (slug da
 * coluna => valor), de onde saem as variáveis {{coluna}} do texto.
 *
 * `skipped` = não enviado de propósito: o endereço está em "Não enviar para"
 * na hora do envio (a conferência é no envio, não na importação, para valer
 * também quem se descadastrou depois de a lista subir).
 */
class EmailCampaignRecipient extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    /** @var array<string, string> */
    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'Na fila',
        self::STATUS_SENT => 'Enviado',
        self::STATUS_FAILED => 'Falhou',
        self::STATUS_SKIPPED => 'Não enviado (descadastrado)',
    ];

    public const STATUS_PILLS = [
        self::STATUS_PENDING => 'off',
        self::STATUS_SENT => 'ok',
        self::STATUS_FAILED => 'danger',
        self::STATUS_SKIPPED => 'warn',
    ];

    protected $fillable = [
        'email_campaign_id',
        'email',
        'name',
        'data',
        'status',
        'attempts',
        'error',
        'sent_at',
    ];

    protected $casts = [
        'data' => 'array',
        'sent_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(EmailCampaign::class, 'email_campaign_id');
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function statusPill(): string
    {
        return self::STATUS_PILLS[$this->status] ?? 'off';
    }
}
