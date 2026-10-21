<?php

namespace App\Models;

use App\Support\EmailMask;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * O link de assinatura remota: enviado por e-mail, abre a tela de quem assina
 * no site externo, sem login. Ver RemoteSignatureService.
 *
 * O token em claro existe só no e-mail. O banco guarda o sha256 — quem lê a
 * tabela não abre link nenhum.
 *
 * Vencido não é um status gravado: é `active` com `expires_at` no passado
 * (`isExpired()`). Assim o link não depende de um comando agendado para
 * deixar de valer na hora certa.
 *
 * @property int $id
 * @property string $status
 * @property string $email
 * @property \Illuminate\Support\Carbon $expires_at
 */
class SignatureRemoteLink extends Model
{
    public const STATUS_ACTIVE = 'active';
    /** A pessoa assinou ou recusou por este link. */
    public const STATUS_USED = 'used';
    public const STATUS_REVOKED = 'revoked';
    /** Outro link foi enviado ao mesmo signatário. */
    public const STATUS_SUPERSEDED = 'superseded';
    /** Esgotou as tentativas de CPF. */
    public const STATUS_LOCKED = 'locked';

    public const STATUS_LABELS = [
        self::STATUS_ACTIVE => 'Ativo',
        self::STATUS_USED => 'Usado',
        self::STATUS_REVOKED => 'Revogado',
        self::STATUS_SUPERSEDED => 'Substituído',
        self::STATUS_LOCKED => 'Bloqueado por tentativas de CPF',
    ];

    protected $fillable = [
        'signature_signer_id',
        'token_hash',
        'email',
        'status',
        'expires_at',
        'identity_attempts',
        'opened_count',
        'last_opened_at',
        'used_at',
        'sent_by',
        'sent_by_name',
        'revoked_at',
        'revoked_by',
        'revoked_by_name',
    ];

    /**
     * Default no MODEL — ver o mesmo comentário em SignatureDocument.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_ACTIVE,
        'identity_attempts' => 0,
        'opened_count' => 0,
    ];

    protected $casts = [
        'signature_signer_id' => 'integer',
        'expires_at' => 'datetime',
        'identity_attempts' => 'integer',
        'opened_count' => 'integer',
        'last_opened_at' => 'datetime',
        'used_at' => 'datetime',
        'sent_by' => 'integer',
        'revoked_at' => 'datetime',
        'revoked_by' => 'integer',
    ];

    protected $hidden = [
        'token_hash',
    ];

    /**
     * @return BelongsTo<SignatureSigner, SignatureRemoteLink>
     */
    public function signer(): BelongsTo
    {
        return $this->belongsTo(SignatureSigner::class, 'signature_signer_id');
    }

    /**
     * As aberturas do link — cada uma, uma liberação com sessão própria.
     *
     * @return HasMany<SignatureRequest>
     */
    public function requests(): HasMany
    {
        return $this->hasMany(SignatureRequest::class, 'signature_remote_link_id')->orderByDesc('id');
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_ACTIVE && $this->expires_at->isPast();
    }

    /** Ainda abre a tela de assinatura? (O documento pode barrar por outro motivo.) */
    public function isUsable(): bool
    {
        return $this->status === self::STATUS_ACTIVE && !$this->isExpired();
    }

    public function statusLabel(): string
    {
        if ($this->isExpired()) {
            return 'Vencido';
        }

        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    /** E-mail mascarado para tela, trilha e manifesto: m***a@exemplo.com.br. */
    public function maskedEmail(): string
    {
        return EmailMask::of($this->email);
    }
}
