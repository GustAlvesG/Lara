<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Revisão automática de um contrato pelo Claude, pedida por quem redige ou
 * pela Gerência: o resumo das inconsistências e os apontamentos com sugestão de alteração.
 * Só orienta — não aprova nem devolve nada. Ver ContractAiReviewer.
 */
class SignatureContractAiReview extends Model
{
    public const VERDICT_OK = 'sem_ressalvas';
    public const VERDICT_ADJUST = 'ajustes_recomendados';
    public const VERDICT_REVIEW = 'revisar_antes_de_aprovar';

    public const VERDICT_LABELS = [
        self::VERDICT_OK => 'Sem ressalvas',
        self::VERDICT_ADJUST => 'Ajustes recomendados',
        self::VERDICT_REVIEW => 'Revisar antes de aprovar',
    ];

    public const SEVERITIES = ['alta', 'media', 'baixa'];

    /** Revisão jurídica (04/11/2026): o nível de risco do contrato para o clube. */
    public const LEGAL_RISK_LOW = 'baixo';
    public const LEGAL_RISK_MEDIUM = 'medio';
    public const LEGAL_RISK_HIGH = 'alto';

    public const LEGAL_RISK_LABELS = [
        self::LEGAL_RISK_LOW => 'Risco jurídico baixo',
        self::LEGAL_RISK_MEDIUM => 'Risco jurídico médio',
        self::LEGAL_RISK_HIGH => 'Risco jurídico alto',
    ];

    /** Por que um trecho fica fora da análise, a menos que quem pede o inclua. */
    public const SENSITIVE_LABELS = [
        'valores' => 'valores',
        'cpf' => 'CPF',
        'documentos' => 'documentos',
        'endereco' => 'endereço',
    ];

    protected $fillable = [
        'signature_contract_id',
        'content_hash',
        'model',
        'verdict',
        'summary',
        'findings',
        'withheld',
        'extras',
        'legal',
        'input_tokens',
        'output_tokens',
        'requested_by',
        'requested_by_name',
    ];

    protected $casts = [
        'signature_contract_id' => 'integer',
        'findings' => 'array',
        'withheld' => 'array',
        'extras' => 'array',
        'legal' => 'array',
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'requested_by' => 'integer',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(SignatureContract::class, 'signature_contract_id');
    }

    /**
     * Os trechos anexados ao pedido (04/11/2026), como foram enviados.
     *
     * @return array<int, array{title: string, text: string}>
     */
    public function extraParts(): array
    {
        return array_values($this->extras ?? []);
    }

    /**
     * A revisão jurídica (só nas pedidas pela Gerência), ou null.
     *
     * @return array{risk: string, summary: string, weaknesses: array<int, array{clausula: int, point: string, suggestion: string}>, seek_lawyer: bool}|null
     */
    public function legalReview(): ?array
    {
        return is_array($this->legal) && isset($this->legal['risk']) ? $this->legal : null;
    }

    public function legalRiskLabel(): ?string
    {
        $juridica = $this->legalReview();

        return $juridica ? (self::LEGAL_RISK_LABELS[$juridica['risk']] ?? $juridica['risk']) : null;
    }

    /** Para a pílula da tela: ok | warn | danger. */
    public function legalRiskKind(): string
    {
        return match ($this->legalReview()['risk'] ?? null) {
            self::LEGAL_RISK_HIGH => 'danger',
            self::LEGAL_RISK_MEDIUM => 'warn',
            default => 'ok',
        };
    }

    /** Risco alto: a tela orienta procurar um jurídico especializado. */
    public function seeksLawyer(): bool
    {
        return (bool) ($this->legalReview()['seek_lawyer'] ?? false);
    }

    /** Chaves dos trechos que ficaram de fora, em ordem (para comparar pedidos). */
    public function withheldKeys(): array
    {
        $chaves = array_column($this->withheld ?? [], 'key');
        sort($chaves);

        return $chaves;
    }

    public function verdictLabel(): string
    {
        return self::VERDICT_LABELS[$this->verdict] ?? $this->verdict;
    }

    /** Para a pílula da tela: ok | warn | danger. */
    public function verdictKind(): string
    {
        return match ($this->verdict) {
            self::VERDICT_OK => 'ok',
            self::VERDICT_REVIEW => 'danger',
            default => 'warn',
        };
    }
}
