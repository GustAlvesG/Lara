<?php

namespace App\Models;

use App\Services\Signature\SignatureFieldTypes;
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
 * @property ?string $report_path    relatório de validação do gov.br (PDF à parte)
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
        'signature_layout_id',
        'title',
        'data',
        'attachment_requirements',
        'signer_field_keys',
        'review_status',
        'reviewed_at',
        'signing_data',
        'signing_answered_at',
        'body_snapshot',
        'source_path',
        'source_sha256',
        'status',
        'original_path',
        'original_sha256',
        'frozen_at',
        'final_path',
        'final_sha256',
        'report_path',
        'report_sha256',
        'finalized_at',
        'archive_path',
        'archived_at',
        'validation_code',
        'created_by',
        'created_by_name',
        'canceled_reason',
        'expires_at',
        'govbr_sent_at',
        'govbr_check_id',
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
        'signature_layout_id' => 'integer',
        'data' => 'array',
        'attachment_requirements' => 'array',
        'signer_field_keys' => 'array',
        'reviewed_at' => 'datetime',
        'signing_data' => 'array',
        'signing_answered_at' => 'datetime',
        'frozen_at' => 'datetime',
        'finalized_at' => 'datetime',
        'archived_at' => 'datetime',
        'expires_at' => 'datetime',
        'created_by' => 'integer',
        'govbr_sent_at' => 'datetime',
        'govbr_check_id' => 'integer',
    ];

    /**
     * @return BelongsTo<SignatureTemplate, SignatureDocument>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(SignatureTemplate::class, 'signature_template_id');
    }

    /**
     * O papel timbrado vigente quando o documento foi congelado. Null para
     * documento congelado sem papel timbrado — sai com o cabeçalho padrão,
     * para sempre, mesmo que a empresa cadastre um depois.
     *
     * @return BelongsTo<SignatureLayout, SignatureDocument>
     */
    public function layout(): BelongsTo
    {
        return $this->belongsTo(SignatureLayout::class, 'signature_layout_id');
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

    /**
     * PDFs assinados pelo gov.br enviados para conferência, o mais recente
     * primeiro.
     *
     * @return HasMany<SignatureGovbrCheck>
     */
    /**
     * @return HasMany<SignatureAttachment>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(SignatureAttachment::class)->orderBy('id');
    }

    /**
     * Os anexos pedidos: os do modelo, depois os deste documento. Cada item
     * diz de onde veio (`origin`: modelo ou documento).
     *
     * @return array<int, array{key: string, label: string, required: bool, origin: string}>
     */
    public function attachmentRequirements(): array
    {
        $doModelo = array_map(
            fn(array $item) => $item + ['origin' => 'modelo'],
            $this->template?->declaredAttachments() ?? [],
        );

        $doDocumento = collect($this->attachment_requirements ?? [])
            ->filter(fn($a) => ($a['key'] ?? '') !== '' && ($a['label'] ?? '') !== '')
            ->map(fn($a) => [
                'key' => (string) $a['key'],
                'label' => (string) $a['label'],
                'required' => (bool) ($a['required'] ?? false),
                'origin' => 'documento',
            ])
            ->values()
            ->all();

        return array_merge($doModelo, $doDocumento);
    }

    /**
     * Os rótulos dos anexos OBRIGATÓRIOS que ainda não têm arquivo — o que
     * trava o congelamento.
     *
     * @return array<int, string>
     */
    public function missingAttachments(): array
    {
        $enviados = $this->attachments()->pluck('requirement_key')->filter()->unique()->all();

        return collect($this->attachmentRequirements())
            ->filter(fn(array $item) => $item['required'] && !in_array($item['key'], $enviados, true))
            ->pluck('label')
            ->values()
            ->all();
    }

    public function govbrChecks(): HasMany
    {
        return $this->hasMany(SignatureGovbrCheck::class)->orderByDesc('id');
    }

    /**
     * As revisões internas, a mais recente primeiro.
     *
     * @return HasMany<SignatureReview>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(SignatureReview::class)->orderByDesc('id');
    }

    /**
     * Quando o documento fica disponível para revisão: o começo do dia
     * seguinte à conclusão. Null enquanto não concluído.
     */
    public function reviewAvailableAt(): ?\Illuminate\Support\Carbon
    {
        return $this->finalized_at?->copy()->addDay()->startOfDay();
    }

    /** Concluído e ainda sem revisão "em ordem": está na fila. */
    public function awaitsReview(): bool
    {
        return $this->status === self::STATUS_FINALIZED
            && $this->review_status !== SignatureReview::RESULT_OK;
    }

    public function reviewStatusLabel(): string
    {
        if ($this->status !== self::STATUS_FINALIZED) {
            return 'Não se aplica';
        }

        return match ($this->review_status) {
            SignatureReview::RESULT_OK => 'Revisado',
            SignatureReview::RESULT_ISSUES => 'Revisado com pendência',
            default => 'Aguardando revisão',
        };
    }

    /**
     * Quem acompanhou a assinatura deste documento: quem o gerou, quem gerou
     * algum QR, quem enviou convite ou conferiu arquivo do gov.br. Nenhum
     * deles revisa (salvo com a permissão de coordenação).
     *
     * @return array<int, int>
     */
    public function involvedUserIds(): array
    {
        $signatarios = $this->signers()->pluck('id');

        $ids = collect([$this->created_by])
            ->merge(SignatureRequest::whereIn('signature_signer_id', $signatarios)->pluck('created_by'))
            ->merge(SignatureGovbrInvite::whereIn('signature_signer_id', $signatarios)->pluck('sent_by'))
            ->merge($this->govbrChecks()->pluck('checked_by'));

        return $ids->filter()->map(fn($id) => (int) $id)->unique()->values()->all();
    }

    /**
     * A conferência do gov.br cujo arquivo é o PDF final — o que voltou com
     * todas as assinaturas.
     *
     * @return BelongsTo<SignatureGovbrCheck, SignatureDocument>
     */
    public function govbrFinalCheck(): BelongsTo
    {
        return $this->belongsTo(SignatureGovbrCheck::class, 'govbr_check_id');
    }

    /**
     * O arquivo que o PRÓXIMO signatário assina pelo gov.br: o devolvido pela
     * última conferência que registrou assinatura — as assinaturas se somam
     * no mesmo PDF. Sem nenhuma ainda, o original.
     *
     * @return array{path: string, check: ?SignatureGovbrCheck}
     */
    public function govbrFileToSign(): array
    {
        $ultima = $this->govbrChecks()->get()
            ->first(fn(SignatureGovbrCheck $c) => $c->valid && $c->concludedNames() !== []);

        return $ultima
            ? ['path' => $ultima->file_path, 'check' => $ultima]
            : ['path' => (string) $this->original_path, 'check' => null];
    }

    /** Preparado para o gov.br: assina-se por lá, e o tablet não libera mais. */
    public function isGovbr(): bool
    {
        return $this->govbr_sent_at !== null;
    }

    /**
     * Por que este documento não pode ser assinado pelo gov.br. null = pode.
     *
     * O gov.br assina o PDF como ele está: o que o tablet acrescenta na hora
     * (respostas de quem assina, visto em cada página) não tem como entrar.
     * E um documento é assinado por um caminho só — a assinatura do tablet
     * vai num PDF re-renderizado, a do gov.br no arquivo original, e não há
     * um arquivo que contenha as duas.
     */
    public function govbrBlockReason(): ?string
    {
        if (!$this->isFrozen()) {
            return 'Congele o documento antes: é o congelamento que gera o PDF a ser assinado.';
        }

        if ($this->status !== self::STATUS_AWAITING_SIGNATURE) {
            return 'Documento em ' . mb_strtolower($this->statusLabel()) . ': não há assinatura a colher.';
        }

        $template = $this->template;

        if ($this->signerFields() !== []) {
            return 'Há campos marcados para perguntar a quem assina, e as perguntas só existem no tablet: este '
                . 'documento só pode ser assinado no balcão. Para o gov.br, o atendente preenche esses campos.';
        }

        if ($template?->requires_initials) {
            return 'Este documento exige visto em todas as páginas, que só existe no tablet: ele só pode ser assinado no balcão.';
        }

        $peloTablet = $this->signers()
            ->where('status', SignatureSigner::STATUS_SIGNED)
            ->whereNull('govbr_check_id')
            ->exists();

        if ($peloTablet) {
            return 'Já há assinatura no tablet neste documento: os demais signatários assinam no tablet também.';
        }

        return null;
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    /**
     * Documento PRONTO, enviado em PDF: entra na íntegra, e o sistema só
     * carimba assinatura e visto por cima. Não tem texto de modelo.
     */
    public function isUploaded(): bool
    {
        return $this->source_path !== null;
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
     * O primeiro signatário pendente, pela ordem da lista. Não há ordem
     * obrigatória: é só a sugestão que as telas trazem marcada.
     */
    public function nextSigner(): ?SignatureSigner
    {
        return $this->signers()
            ->where('status', SignatureSigner::STATUS_PENDING)
            ->first();
    }

    /**
     * Os dados do ato da assinatura ainda podem mudar?
     *
     * Podem enquanto o documento aguarda assinatura e NINGUÉM assinou. A
     * primeira assinatura é a fronteira de verdade: a partir dela existe uma
     * pessoa que leu e assinou aquele texto, e ele não muda mais — nem para o
     * signatário seguinte.
     */
    public function signingDataIsOpen(): bool
    {
        return $this->status === self::STATUS_AWAITING_SIGNATURE
            && !$this->signers()->where('status', SignatureSigner::STATUS_SIGNED)->exists();
    }

    /**
     * O modelo pergunta algo a quem assina e a resposta ainda não veio.
     * Enquanto for assim, o documento não pode ser assinado: o texto que a
     * pessoa leria ainda tem lacunas.
     */
    public function signingFormPending(): bool
    {
        return $this->signing_answered_at === null
            && $this->signerFields() !== [];
    }

    /**
     * As chaves dos campos que o atendente marcou para perguntar a quem assina.
     *
     * @return array<int, string>
     */
    public function signerFieldKeys(): array
    {
        return array_values(array_map('strval', (array) ($this->signer_field_keys ?? [])));
    }

    /**
     * Os campos do modelo, com quem responde cada um NESTE documento: o modelo
     * declara os campos; o atendente decide, no preenchimento, quais vão ao
     * tablet (`ask_signer`). Automático não é perguntado a ninguém.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fieldDefinitions(): array
    {
        $perguntados = $this->signerFieldKeys();

        return array_map(fn(array $v) => $v + [
            'ask_signer' => !SignatureFieldTypes::isAutomatic($v['type']) && in_array($v['key'], $perguntados, true),
        ], $this->template?->declaredVariables() ?? []);
    }

    /**
     * Os campos que o ATENDENTE preenche neste documento.
     *
     * @return array<int, array<string, mixed>>
     */
    public function attendantFields(): array
    {
        return array_values(array_filter(
            $this->fieldDefinitions(),
            fn($v) => !$v['ask_signer'] && !SignatureFieldTypes::isAutomatic($v['type']),
        ));
    }

    /**
     * Os campos que QUEM ASSINA responde, no tablet, antes de ler o documento.
     *
     * @return array<int, array<string, mixed>>
     */
    public function signerFields(): array
    {
        return array_values(array_filter($this->fieldDefinitions(), fn($v) => $v['ask_signer']));
    }

    /**
     * Os campos que o servidor resolve sozinho no ato da assinatura.
     *
     * @return array<int, array<string, mixed>>
     */
    public function automaticFields(): array
    {
        return array_values(array_filter(
            $this->fieldDefinitions(),
            fn($v) => SignatureFieldTypes::isAutomatic($v['type']),
        ));
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
     * @return array<int, array{name: string, cpf: string, status: string, signed_at: ?string, via: ?string}>
     */
    public function publicSigners(): array
    {
        return $this->signers()->get()
            ->map(fn(SignatureSigner $s) => [
                'name' => $s->name,
                'cpf' => Cpf::mask($s->cpf),
                'status' => $s->statusLabel(),
                'signed_at' => $s->signed_at?->format('d/m/Y H:i'),
                // Por onde assinou — o gov.br diz mais a quem valida que "assinou".
                'via' => $s->status === SignatureSigner::STATUS_SIGNED
                    ? ($s->signedViaGovbr() ? 'gov.br' : 'tablet')
                    : null,
            ])
            ->all();
    }
}
