<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Revisão automática de um contrato pelo Claude, pedida pela Gerência: o
 * resumo das inconsistências e os apontamentos com sugestão de alteração.
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

    /** Por que um trecho fica fora da análise, a menos que o gerente o inclua. */
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
        'input_tokens',
        'output_tokens',
        'requested_by',
        'requested_by_name',
    ];

    protected $casts = [
        'signature_contract_id' => 'integer',
        'findings' => 'array',
        'withheld' => 'array',
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'requested_by' => 'integer',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(SignatureContract::class, 'signature_contract_id');
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
