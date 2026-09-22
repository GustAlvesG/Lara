<?php

namespace App\Models;

use App\Support\Cpf;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * O documento de um atendimento: um modelo preenchido, congelado e assinado.
 *
 * Ciclo: `draft` (ainda editável) → `awaiting_signature` (congelado, com PDF e
 * hash) → `signed` (todos assinaram) → `finalized` (PDF final com manifesto).
 * Saídas: `refused`, `canceled`, `expired`.
 *
 * Quem move o documento de um estado a outro é SignatureStateMachine — nunca
 * um `update(['status' => ...])` solto, que passaria por fora da auditoria.
 *
 * @property int $id
 * @property string $status
 * @property ?string $original_sha256
 * @property ?string $final_sha256
 */
class SignatureDocument extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_AWAITING_SIGNATURE = 'awaiting_signature';
    public const STATUS_SIGNED = 'signed';
    public const STATUS_FINALIZED = 'finalized';
    public const STATUS_REFUSED = 'refused';
    public const STATUS_CANCELED = 'canceled';
    public const STATUS_EXPIRED = 'expired';

    /** Rótulos das telas — o banco guarda a chave, a tela mostra isto. */
    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Rascunho',
        self::STATUS_AWAITING_SIGNATURE => 'Aguardando assinatura',
        self::STATUS_SIGNED => 'Assinado',
        self::STATUS_FINALIZED => 'Finalizado',
        self::STATUS_REFUSED => 'Recusado',
        self::STATUS_CANCELED => 'Cancelado',
        self::STATUS_EXPIRED => 'Expirado',
    ];

    /** Estados em que o documento ainda espera alguma coisa de alguém. */
    public const STATUS_OPEN = [
        self::STATUS_DRAFT,
        self::STATUS_AWAITING_SIGNATURE,
    ];

    protected $fillable = [
        'signature_template_id',
        'template_version',
        'title',
        'data',
        'body_snapshot',
        'status',
        'original_path',
        'original_sha256',
        'frozen_at',
        'final_path',
        'final_sha256',
        'finalized_at',
        'validation_code',
        'location',
        'created_by',
        'canceled_reason',
        'expires_at',
    ];

    /**
     * Default no MODEL, e não só no schema: o documento é consultado pelo
     * status logo depois de criado (a máquina de estados parte dele), e um
     * default que só existe no banco chegaria como null.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_DRAFT,
    ];

    protected $casts = [
        'signature_template_id' => 'integer',
        'template_version' => 'integer',
        'data' => 'array',
        'frozen_at' => 'datetime',
        'finalized_at' => 'datetime',
        'expires_at' => 'datetime',
        'created_by' => 'integer',
    ];

    /**
     * @return BelongsTo<SignatureTemplate, SignatureDocument>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(SignatureTemplate::class, 'signature_template_id');
    }

    /**
     * @return HasMany<SignatureSigner>
     */
    public function signers(): HasMany
    {
        return $this->hasMany(SignatureSigner::class)->orderBy('position')->orderBy('id');
    }

    /**
     * @return HasMany<SignatureAuditEvent>
     */
    public function auditEvents(): HasMany
    {
        return $this->hasMany(SignatureAuditEvent::class)->orderBy('id');
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    /** Congelado: texto, dados e PDF original não mudam mais. */
    public function isFrozen(): bool
    {
        return $this->frozen_at !== null;
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::STATUS_OPEN, true);
    }

    /**
     * Por que este documento não pode mais ser editado. Devolve null quando a
     * edição é permitida — o mesmo formato de `releaseBlockReason()` dos
     * contratos de freelancer, para a tela e a rota darem a MESMA resposta.
     */
    public function editBlockReason(): ?string
    {
        if ($this->isFrozen()) {
            return 'Documento já congelado: o texto e os dados não mudam mais. '
                . 'Para corrigir algo, cancele e gere um novo documento.';
        }

        if ($this->status !== self::STATUS_DRAFT) {
            return 'Documento em ' . mb_strtolower($this->statusLabel()) . ': não pode ser editado.';
        }

        return null;
    }

    /**
     * O próximo signatário da fila — é para ele que o atendente gera o QR.
     * Signatários são atendidos em sequência, na ordem de `position`.
     */
    public function nextSigner(): ?SignatureSigner
    {
        return $this->signers()
            ->where('status', SignatureSigner::STATUS_PENDING)
            ->first();
    }

    /** Todos assinaram? É o que move o documento para `signed`. */
    public function allSignersSigned(): bool
    {
        $signers = $this->relationLoaded('signers') ? $this->signers : $this->signers()->get();

        return $signers->isNotEmpty()
            && $signers->every(fn(SignatureSigner $s) => $s->status === SignatureSigner::STATUS_SIGNED);
    }

    /**
     * Os signatários como vão para a página PÚBLICA de validação: nome e CPF
     * mascarado, e nada mais. Sem foto, sem traço, sem contato.
     *
     * @return array<int, array{name: string, cpf: string, status: string, signed_at: ?string}>
     */
    public function publicSigners(): array
    {
        return $this->signers()->get()
            ->map(fn(SignatureSigner $s) => [
                'name' => $s->name,
                'cpf' => Cpf::mask($s->cpf),
                'status' => $s->statusLabel(),
                'signed_at' => $s->signed_at?->format('d/m/Y H:i'),
            ])
            ->all();
    }
}
