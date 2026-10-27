<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Contrato redigido no Lara (Assinaturas → Contratos).
 *
 * Ciclo: rascunho → em validação (Gerência) → devolvido → … → aprovado. Cada
 * envio à Gerência congela uma versão (`SignatureContractVersion`); o
 * `content` daqui é a cópia de trabalho. Aprovado, o contrato ganha número e
 * gera um documento de assinatura em RASCUNHO (`signature_document_id`) — a
 * partir daí vale o circuito comum do módulo. Ver ContractService.
 *
 * @property int $id
 * @property string $status
 * @property array<string, mixed>|null $content
 */
class SignatureContract extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_IN_REVIEW = 'in_review';
    public const STATUS_RETURNED = 'returned';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_CANCELED = 'canceled';

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Rascunho',
        self::STATUS_IN_REVIEW => 'Em validação',
        self::STATUS_RETURNED => 'Devolvido para ajustes',
        self::STATUS_APPROVED => 'Aprovado',
        self::STATUS_CANCELED => 'Cancelado',
    ];

    public const KIND_CONTRACT = 'contract';
    public const KIND_ADDENDUM = 'addendum';

    protected $fillable = [
        'sector_id',
        'sector_name',
        'kind',
        'parent_id',
        'addendum_seq',
        'number_seq',
        'number_year',
        'number',
        'title',
        'status',
        'content',
        'current_version',
        'approved_version_id',
        'signature_document_id',
        'created_by',
        'created_by_name',
        'submitted_at',
        'approved_at',
        'approved_by',
        'approved_by_name',
        'canceled_at',
    ];

    protected $attributes = [
        'kind' => self::KIND_CONTRACT,
        'status' => self::STATUS_DRAFT,
        'current_version' => 0,
    ];

    protected $casts = [
        'sector_id' => 'integer',
        'parent_id' => 'integer',
        'addendum_seq' => 'integer',
        'number_seq' => 'integer',
        'number_year' => 'integer',
        'content' => 'array',
        'current_version' => 'integer',
        'approved_version_id' => 'integer',
        'signature_document_id' => 'integer',
        'created_by' => 'integer',
        'approved_by' => 'integer',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'canceled_at' => 'datetime',
    ];

    /** @return HasMany<SignatureContractVersion> */
    public function versions(): HasMany
    {
        return $this->hasMany(SignatureContractVersion::class)->orderByDesc('version');
    }

    /** @return HasMany<SignatureContractComment> */
    public function comments(): HasMany
    {
        return $this->hasMany(SignatureContractComment::class)->orderBy('id');
    }

    /** @return HasMany<SignatureContractEvent> */
    public function events(): HasMany
    {
        return $this->hasMany(SignatureContractEvent::class)->orderByDesc('id');
    }

    /** @return HasMany<SignatureContractAttachment> */
    public function attachments(): HasMany
    {
        return $this->hasMany(SignatureContractAttachment::class)->orderBy('id');
    }

    /** Revisões automáticas pelo Claude, a mais recente primeiro. */
    public function aiReviews(): HasMany
    {
        return $this->hasMany(SignatureContractAiReview::class)->orderByDesc('id');
    }

    /** @return BelongsTo<SignatureDocument, self> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(SignatureDocument::class, 'signature_document_id');
    }

    /** O contrato de que este é aditivo. */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** Os aditivos deste contrato. */
    public function addenda(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('addendum_seq');
    }

    /** A versão que está (ou esteve por último) com a Gerência. */
    public function latestVersion(): ?SignatureContractVersion
    {
        return $this->versions()->first();
    }

    /**
     * Contratos que este usuário enxerga: os dos setores dele; a Gerência
     * (que valida todos) enxerga tudo.
     *
     * @param  array<int, int>  $sectorIds
     */
    public function scopeVisibleTo(Builder $query, array $sectorIds, bool $isManagement): Builder
    {
        return $isManagement
            ? $query
            : $query->whereIn('sector_id', $sectorIds === [] ? [0] : $sectorIds);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function isAddendum(): bool
    {
        return $this->kind === self::KIND_ADDENDUM;
    }

    /** A cópia de trabalho pode ser editada pelo setor? */
    public function isEditableBySector(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_RETURNED], true);
    }

    /** O número, ou o que mostrar enquanto ele não existe (é dado na aprovação). */
    public function displayNumber(): string
    {
        return $this->number ?: 'Sem número (sai na aprovação)';
    }

    /** O nome da parte principal (a primeira contratada), para listas e títulos. */
    public function counterpartyName(): ?string
    {
        foreach ($this->content['parties'] ?? [] as $parte) {
            if (($parte['role'] ?? null) === 'contratada' && filled($parte['name'] ?? null)) {
                return (string) $parte['name'];
            }
        }

        return null;
    }
}
