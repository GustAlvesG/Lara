<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Um evento da trilha de auditoria. A tabela é SOMENTE INSERÇÃO.
 *
 * Este model recusa update e delete. A recusa é de propósito uma EXCEÇÃO, e
 * não um `return false` silencioso: um save que não grava e não reclama é pior
 * que o estrago que ele evita — o chamador segue achando que gravou.
 *
 * A imutabilidade real, contra quem escreve por fora da aplicação, vem da
 * cadeia de hash (ver SignatureAuditor) e do GRANT do banco em produção.
 *
 * @property string $event
 * @property ?array $payload
 * @property string $hash
 */
class SignatureAuditEvent extends Model
{
    /** Nunca atualizada. */
    public const UPDATED_AT = null;

    /* Eventos. As chaves são inglês, como o resto do código; os rótulos em
     | pt-BR são o que aparece no manifesto e nas telas. */
    public const EVENT_CREATED = 'created';
    public const EVENT_FROZEN = 'frozen';
    public const EVENT_QR_ISSUED = 'qr_issued';
    public const EVENT_QR_REISSUED = 'qr_reissued';
    public const EVENT_QR_CONSUMED = 'qr_consumed';
    public const EVENT_QR_REUSE_BLOCKED = 'qr_reuse_blocked';
    public const EVENT_QR_IP_BLOCKED = 'qr_ip_blocked';
    public const EVENT_ACCESS_DENIED = 'access_denied';
    public const EVENT_VIEWED = 'viewed';
    public const EVENT_IDENTITY_CONFIRMED = 'identity_confirmed';
    public const EVENT_IDENTITY_FAILED = 'identity_failed';
    public const EVENT_SIGNED = 'signed';
    public const EVENT_REFUSED = 'refused';
    public const EVENT_CANCELED = 'canceled';
    public const EVENT_EXPIRED = 'expired';
    public const EVENT_FINALIZED = 'finalized';
    public const EVENT_FINALIZATION_FAILED = 'finalization_failed';
    public const EVENT_COPY_SENT = 'copy_sent';

    public const EVENT_LABELS = [
        self::EVENT_CREATED => 'Documento criado',
        self::EVENT_FROZEN => 'Documento congelado',
        self::EVENT_QR_ISSUED => 'QR gerado',
        self::EVENT_QR_REISSUED => 'QR regerado',
        self::EVENT_QR_CONSUMED => 'QR lido pelo tablet',
        self::EVENT_QR_REUSE_BLOCKED => 'Releitura de QR bloqueada',
        self::EVENT_QR_IP_BLOCKED => 'Leitura bloqueada por IP',
        self::EVENT_ACCESS_DENIED => 'Acesso negado à sessão',
        self::EVENT_VIEWED => 'Documento visualizado',
        self::EVENT_IDENTITY_CONFIRMED => 'Identidade confirmada',
        self::EVENT_IDENTITY_FAILED => 'Identidade não confirmada',
        self::EVENT_SIGNED => 'Assinado',
        self::EVENT_REFUSED => 'Recusado',
        self::EVENT_CANCELED => 'Cancelado',
        self::EVENT_EXPIRED => 'Expirado',
        self::EVENT_FINALIZED => 'Finalizado',
        self::EVENT_FINALIZATION_FAILED => 'Falha na finalização',
        self::EVENT_COPY_SENT => 'Via enviada',
    ];

    public const ACTOR_USER = 'user';
    public const ACTOR_KIOSK = 'kiosk';
    public const ACTOR_SYSTEM = 'system';

    protected $fillable = [
        'signature_document_id',
        'signature_signer_id',
        'signature_request_id',
        'event',
        'payload',
        'actor_type',
        'actor_id',
        'ip',
        'user_agent',
        'occurred_at',
        'previous_hash',
        'hash',
    ];

    protected $casts = [
        'signature_document_id' => 'integer',
        'signature_signer_id' => 'integer',
        'signature_request_id' => 'integer',
        'payload' => 'array',
        'actor_id' => 'integer',
        'occurred_at' => 'datetime',
    ];

    /**
     * Fecha as duas portas do Eloquent: alterar uma linha existente e apagá-la.
     *
     * `saving` cobre update e também o `forceFill(...)->save()` de um registro
     * já existente; `deleting` cobre delete e forceDelete.
     */
    protected static function booted(): void
    {
        static::updating(function () {
            throw new RuntimeException(
                'signature_audit_events é somente inserção: um evento de auditoria não pode ser alterado.'
            );
        });

        static::saving(function (SignatureAuditEvent $event) {
            if ($event->exists && $event->isDirty()) {
                throw new RuntimeException(
                    'signature_audit_events é somente inserção: um evento de auditoria não pode ser alterado.'
                );
            }
        });

        static::deleting(function () {
            throw new RuntimeException(
                'signature_audit_events é somente inserção: um evento de auditoria não pode ser apagado.'
            );
        });
    }

    /**
     * @return BelongsTo<SignatureDocument, SignatureAuditEvent>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(SignatureDocument::class, 'signature_document_id');
    }

    public function label(): string
    {
        return self::EVENT_LABELS[$this->event] ?? $this->event;
    }

    /**
     * Os campos que entram no hash, na ORDEM FIXA em que entram.
     *
     * A ordem é parte do formato: mudá-la invalida a conferência de tudo o que
     * já foi gravado. O `hash` da própria linha fica de fora, obviamente, e o
     * `created_at` também — ele é conveniência de consulta, enquanto
     * `occurred_at` é o fato.
     *
     * @return array<string, mixed>
     */
    public function hashableAttributes(): array
    {
        return [
            'signature_document_id' => (int) $this->signature_document_id,
            'signature_signer_id' => $this->signature_signer_id === null ? null : (int) $this->signature_signer_id,
            'signature_request_id' => $this->signature_request_id === null ? null : (int) $this->signature_request_id,
            'event' => (string) $this->event,
            'payload' => $this->payload,
            'actor_type' => (string) $this->actor_type,
            'actor_id' => $this->actor_id === null ? null : (int) $this->actor_id,
            'ip' => $this->ip,
            'user_agent' => $this->user_agent,
            'occurred_at' => $this->occurred_at?->format('Y-m-d H:i:s'),
            'previous_hash' => $this->previous_hash,
        ];
    }

    /**
     * O hash desta linha, a partir do hash da anterior.
     *
     * JSON com barras e acentos sem escapar para que o mesmo conteúdo produza
     * o mesmo texto independentemente da versão do PHP.
     */
    public function computeHash(): string
    {
        return hash('sha256', json_encode(
            $this->hashableAttributes(),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
        ));
    }
}
