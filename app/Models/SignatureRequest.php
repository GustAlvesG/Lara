<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A liberação de um documento para o tablet, por QR Code — uma por signatário,
 * uma por vez.
 *
 * O token em claro existe por alguns segundos: é devolvido à tela do atendente
 * para virar QR e nunca mais. O banco guarda só `token_hash`.
 *
 * @property int $id
 * @property string $status
 * @property \Illuminate\Support\Carbon $expires_at
 */
class SignatureRequest extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_CONSUMED = 'consumed';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELED = 'canceled';
    public const STATUS_SUPERSEDED = 'superseded';

    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'Aguardando leitura',
        self::STATUS_CONSUMED => 'Tablet conectado',
        self::STATUS_COMPLETED => 'Concluída',
        self::STATUS_EXPIRED => 'Expirada',
        self::STATUS_CANCELED => 'Cancelada',
        self::STATUS_SUPERSEDED => 'Substituída',
    ];

    /** Prefixo do conteúdo do QR. O leitor do tablet ignora o que não casa. */
    public const TOKEN_PREFIX = 'LARA-SIGN:v1:';

    protected $fillable = [
        'signature_signer_id',
        'token_hash',
        'expires_at',
        'consumed_at',
        'session_hash',
        'session_expires_at',
        'consumed_ip',
        'consumed_user_agent',
        'status',
        'created_by',
    ];

    /**
     * Default no MODEL — ver o mesmo comentário em SignatureDocument.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    protected $casts = [
        'signature_signer_id' => 'integer',
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
        'session_expires_at' => 'datetime',
        'created_by' => 'integer',
    ];

    /**
     * O hash nunca sai em resposta nenhuma. Mesmo sendo hash, é o material com
     * que se compara um token adivinhado.
     */
    protected $hidden = [
        'token_hash',
        'session_hash',
    ];

    /**
     * @return BelongsTo<SignatureSigner, SignatureRequest>
     */
    public function signer(): BelongsTo
    {
        return $this->belongsTo(SignatureSigner::class, 'signature_signer_id');
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    /** Ainda dá para ler o QR? */
    public function isReadable(): bool
    {
        return $this->status === self::STATUS_PENDING
            && $this->expires_at !== null
            && $this->expires_at->isFuture();
    }

    /** A sessão do tablet, já vinculada, continua valendo? */
    public function sessionIsAlive(): bool
    {
        return $this->status === self::STATUS_CONSUMED
            && $this->session_hash !== null
            && $this->session_expires_at !== null
            && $this->session_expires_at->isFuture();
    }

    /** Segundos até o QR expirar — a contagem regressiva da tela do atendente. */
    public function secondsToExpire(): int
    {
        if ($this->expires_at === null) {
            return 0;
        }

        return (int) max(0, now()->diffInSeconds($this->expires_at, false));
    }

    /** Segundos restantes da sessão do tablet. */
    public function secondsToSessionEnd(): int
    {
        if ($this->session_expires_at === null) {
            return 0;
        }

        return (int) max(0, now()->diffInSeconds($this->session_expires_at, false));
    }

    /** O conteúdo que vai dentro do QR, a partir do token em claro. */
    public static function qrPayload(string $plainToken): string
    {
        return self::TOKEN_PREFIX . $plainToken;
    }

    /**
     * Extrai o token de um conteúdo lido pela câmera. Devolve null para
     * qualquer coisa fora do formato — é o que impede o tablet de navegar
     * para uma URL que apareceu num QR qualquer.
     */
    public static function tokenFromQrPayload(?string $payload): ?string
    {
        $payload = trim((string) $payload);

        if (!str_starts_with($payload, self::TOKEN_PREFIX)) {
            return null;
        }

        $token = substr($payload, strlen(self::TOKEN_PREFIX));

        return preg_match('/^[A-Za-z0-9]{64}$/', $token) === 1 ? $token : null;
    }
}
