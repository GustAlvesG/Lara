<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Um PDF assinado pelo gov.br que o atendente enviou para conferência, e o
 * resultado dela. Ver GovbrSignatureValidator e GovbrCheckService.
 *
 * Nesta fase a conferência é só isso — uma conferência: ela NÃO muda o status
 * do documento nem do signatário.
 *
 * @property int $id
 * @property bool $valid
 * @property ?string $base
 * @property array $result
 */
class SignatureGovbrCheck extends Model
{
    public const BASE_LABELS = [
        'original' => 'PDF original',
        'final' => 'PDF assinado no tablet',
    ];

    protected $fillable = [
        'signature_document_id',
        'file_path',
        'file_sha256',
        'file_bytes',
        'valid',
        'base',
        'result',
        'conclusion',
        'checked_by',
        'checked_by_name',
    ];

    protected $casts = [
        'signature_document_id' => 'integer',
        'file_bytes' => 'integer',
        'valid' => 'boolean',
        'result' => 'array',
        'conclusion' => 'array',
        'checked_by' => 'integer',
    ];

    /**
     * @return BelongsTo<SignatureDocument, SignatureGovbrCheck>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(SignatureDocument::class, 'signature_document_id');
    }

    public function baseLabel(): ?string
    {
        return $this->base === null ? null : (self::BASE_LABELS[$this->base] ?? $this->base);
    }

    /** @return array<int, array{key: string, label: string, ok: ?bool, detail: string}> */
    public function checks(): array
    {
        return $this->result['checks'] ?? [];
    }

    /** @return array<int, array<string, mixed>> */
    public function signatures(): array
    {
        return $this->result['signatures'] ?? [];
    }

    /**
     * Quem esta conferência deu como assinado (nomes, na ordem).
     *
     * @return array<int, string>
     */
    public function concludedNames(): array
    {
        return $this->conclusion['assinaram'] ?? [];
    }

    /** Por que a conferência não concluiu nada — null quando concluiu, ou não havia o que concluir. */
    public function conclusionReason(): ?string
    {
        return $this->conclusion['motivo'] ?? null;
    }

    /** Esta conferência fechou o documento: todos assinaram, e o arquivo dela é o final. */
    public function closedDocument(): bool
    {
        return (bool) ($this->conclusion['fechou'] ?? false);
    }
}
