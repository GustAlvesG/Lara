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
        'content_revision',
        'content_saved_by_name',
        'content_saved_at',
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
        'content_revision' => 0,
    ];

    protected $casts = [
        'sector_id' => 'integer',
        'parent_id' => 'integer',
        'addendum_seq' => 'integer',
        'number_seq' => 'integer',
        'number_year' => 'integer',
        'content' => 'array',
        'content_revision' => 'integer',
        'content_saved_at' => 'datetime',
        'current_version' => 'integer',
        'approved_version_id' => 'integer',
        'signature_document_id' => 'integer',
        'created_by' => 'integer',
        'approved_by' => 'integer',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'canceled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (SignatureContract $contract) {
            $contract->parties_search = self::partiesSearchOf($contract->content ?? []);
        });
    }

    /**
     * O que a busca lê das partes: nome, representante e documento (só os
     * caracteres, como o ContractContent guarda). Ver a migration da coluna.
     *
     * @param  array<string, mixed>  $content
     */
    public static function partiesSearchOf(array $content): string
    {
        return collect($content['parties'] ?? [])
            ->flatMap(fn($parte) => [$parte['name'] ?? '', $parte['rep_name'] ?? '', $parte['document'] ?? ''])
            ->filter()
            ->join(' ');
    }

    /**
     * Separa da busca o número do contrato, do jeito que a pessoa digitar:
     * "ctl3/26", "CTL-3/26", "ctl 3". Devolve o resto do texto e o padrão LIKE
     * do número (null se não há). O padrão pega também os aditivos ("… A1").
     *
     * @return array{0: string, 1: ?string}
     */
    public static function splitNumberSearch(?string $busca): array
    {
        $prefixo = (string) config('signature.contracts.number_prefix', 'CTL');
        $regex = '/(?<![\p{L}\d])' . preg_quote($prefixo, '/') . '\s*-?\s*(\d+)(?:\s*\/\s*(\d{2,4}))?(?![\p{L}\d])/iu';

        if (preg_match($regex, (string) $busca, $m) !== 1) {
            return [trim((string) $busca), null];
        }

        $ano = isset($m[2]) ? substr($m[2], -2) : '';

        return [
            trim(str_replace($m[0], ' ', (string) $busca)),
            $prefixo . ' - ' . $m[1] . '/' . $ano . '%',
        ];
    }

    /**
     * Busca da lista de contratos: número em qualquer grafia e cada palavra em
     * título, número, setor, quem redigiu, partes (nome e CNPJ, com ou sem
     * pontuação) ou no texto.
     */
    public function scopeSearch(Builder $query, ?string $busca): Builder
    {
        [$resto, $numero] = self::splitNumberSearch($busca);

        $query->when($numero, fn($q) => $q->where('number', 'like', $numero));

        foreach (array_slice(preg_split('/\s+/u', $resto, -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, 6) as $palavra) {
            $termo = '%' . addcslashes($palavra, '%_\\') . '%';
            $documento = strtoupper((string) preg_replace('/[^0-9A-Za-z]/', '', $palavra));

            $query->where(fn($w) => $w->where('title', 'like', $termo)
                ->orWhere('number', 'like', $termo)
                ->orWhere('sector_name', 'like', $termo)
                ->orWhere('created_by_name', 'like', $termo)
                ->orWhere('parties_search', 'like', $termo)
                ->orWhere('content', 'like', $termo)
                ->when(preg_match('/\d{3}/', $documento) === 1, fn($d) => $d->orWhere('parties_search', 'like', '%' . $documento . '%')));
        }

        return $query;
    }

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

    /**
     * Em assinatura (05/11/2026): aprovado, com o documento em rascunho ou
     * colhendo assinaturas. A aba "Em assinatura" da lista de Contratos.
     */
    public function scopeInSigning(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED)
            ->whereHas('document', fn(Builder $d) => $d->whereIn('status', [
                SignatureDocument::STATUS_DRAFT,
                SignatureDocument::STATUS_AWAITING_SIGNATURE,
                SignatureDocument::STATUS_SIGNED,
            ]));
    }

    /**
     * O andamento da assinatura para a lista e a tela do contrato: quantos
     * assinaram, de quantos, e quem falta. Null sem documento.
     *
     * @return array{status: string, label: string, signed: int, total: int, pending: array<int, string>, refused: ?string}|null
     */
    public function signingProgress(): ?array
    {
        $documento = $this->document;

        if (!$documento) {
            return null;
        }

        $signatarios = $documento->signers->where('role', SignatureSigner::ROLE_SIGNER);
        $ativos = $signatarios->reject(fn(SignatureSigner $s) => $s->status === SignatureSigner::STATUS_CANCELED);
        $recusou = $signatarios->firstWhere('status', SignatureSigner::STATUS_REFUSED);

        return [
            'status' => $documento->status,
            'label' => $documento->statusLabel(),
            'signed' => $ativos->where('status', SignatureSigner::STATUS_SIGNED)->count(),
            'total' => $ativos->count(),
            'pending' => $ativos->where('status', SignatureSigner::STATUS_PENDING)->sortBy('position')
                ->map(fn(SignatureSigner $s) => $s->name . ($s->party_label ? ' (' . $s->party_label . ')' : ''))->values()->all(),
            'refused' => $recusou?->name,
        ];
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
