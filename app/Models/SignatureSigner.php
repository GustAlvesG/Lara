<?php

namespace App\Models;

use App\Support\Cpf;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Quem assina. Um documento pode ter vários — signatário, responsável legal e
 * testemunha —, atendidos em sequência no balcão, um QR por vez.
 *
 * `member_id` é preenchido quando o signatário é associado; um visitante
 * assina só com nome e CPF. Não há foreign key para `members` porque aquele
 * model fixa a conexão `mysql`.
 *
 * @property int $id
 * @property string $cpf dígitos, sem máscara
 * @property string $status
 */
class SignatureSigner extends Model
{
    use HasFactory;

    public const ROLE_SIGNER = 'signer';
    public const ROLE_GUARDIAN = 'guardian';
    public const ROLE_WITNESS = 'witness';

    public const ROLE_LABELS = [
        self::ROLE_SIGNER => 'Signatário',
        self::ROLE_GUARDIAN => 'Responsável legal',
        self::ROLE_WITNESS => 'Testemunha',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_SIGNED = 'signed';
    public const STATUS_REFUSED = 'refused';
    public const STATUS_CANCELED = 'canceled';
    public const STATUS_EXPIRED = 'expired';

    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'Aguardando assinatura',
        self::STATUS_SIGNED => 'Assinou',
        self::STATUS_REFUSED => 'Recusou',
        self::STATUS_CANCELED => 'Cancelado',
        self::STATUS_EXPIRED => 'Expirado',
    ];

    protected $fillable = [
        'signature_document_id',
        'name',
        'cpf',
        'member_id',
        'email',
        'phone',
        'wants_copy',
        'copy_sent_at',
        'role',
        'position',
        'status',
        'signed_at',
        'refused_at',
        'refusal_reason',
    ];

    /**
     * Defaults no MODEL — ver o mesmo comentário em SignatureDocument.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => self::ROLE_SIGNER,
        'position' => 1,
        'status' => self::STATUS_PENDING,
    ];

    protected $casts = [
        'signature_document_id' => 'integer',
        'member_id' => 'integer',
        'position' => 'integer',
        'wants_copy' => 'boolean',
        'copy_sent_at' => 'datetime',
        'signed_at' => 'datetime',
        'refused_at' => 'datetime',
    ];

    /**
     * CPF entra sempre só com dígitos. A normalização mora aqui, e não em cada
     * chamador, porque o CPF chega de três lugares (tela do atendente, busca
     * de associado e tablet) e basta um deles gravar com máscara para a
     * conferência de identidade passar a falhar em silêncio.
     */
    public function setCpfAttribute($value): void
    {
        $this->attributes['cpf'] = Cpf::digits($value);
    }

    /**
     * @return BelongsTo<SignatureDocument, SignatureSigner>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(SignatureDocument::class, 'signature_document_id');
    }

    /**
     * @return HasMany<SignatureRequest>
     */
    public function requests(): HasMany
    {
        return $this->hasMany(SignatureRequest::class)->orderByDesc('id');
    }

    /**
     * @return HasOne<SignatureEvidence>
     */
    public function evidence(): HasOne
    {
        return $this->hasOne(SignatureEvidence::class);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function roleLabel(): string
    {
        return self::ROLE_LABELS[$this->role] ?? $this->role;
    }

    public function maskedCpf(): string
    {
        return Cpf::mask($this->cpf);
    }

    /**
     * Por que este signatário não pode receber um QR agora. null = pode.
     */
    public function releaseBlockReason(): ?string
    {
        if ($this->status === self::STATUS_SIGNED) {
            return 'Este signatário já assinou.';
        }

        if ($this->status !== self::STATUS_PENDING) {
            return 'Signatário em ' . mb_strtolower($this->statusLabel()) . ': não é possível liberar.';
        }

        $document = $this->document;

        if (!$document) {
            return 'Documento não encontrado.';
        }

        if (!$document->isFrozen()) {
            return 'Congele o documento antes de liberar a assinatura.';
        }

        if ($document->status !== SignatureDocument::STATUS_AWAITING_SIGNATURE) {
            return 'Documento em ' . mb_strtolower($document->statusLabel())
                . ': não é possível liberar a assinatura.';
        }

        /*
         | A fila é em ordem: liberar o segundo signatário antes do primeiro
         | produziria um documento em que a testemunha assina um ato que ainda
         | não aconteceu.
         */
        $anterior = static::where('signature_document_id', $this->signature_document_id)
            ->where('position', '<', $this->position)
            ->where('status', self::STATUS_PENDING)
            ->orderBy('position')
            ->first();

        if ($anterior) {
            return 'Antes dele assina ' . $anterior->name . ' (' . $anterior->roleLabel() . ').';
        }

        return null;
    }
}
