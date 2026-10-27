<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Histórico do contrato: quem criou, enviou, devolveu, aprovou, reabriu.
 * Só inserção. Não é a trilha encadeada do documento de assinatura
 * (SignatureAuditEvent) — essa começa quando o documento é gerado.
 */
class SignatureContractEvent extends Model
{
    public const UPDATED_AT = null;

    public const LABELS = [
        'created' => 'Contrato criado',
        'submitted' => 'Enviado à Gerência para validação',
        'reviewer_edited' => 'Ajustado pela Gerência na validação',
        'returned' => 'Devolvido com observações',
        'approved' => 'Aprovado',
        'document_generated' => 'Documento de assinatura gerado (rascunho)',
        'reopened' => 'Reaberto para alteração',
        'canceled' => 'Cancelado',
        'addendum_created' => 'Aditivo criado',
        'comment_resolved' => 'Observação marcada como resolvida',
        'attachment_added' => 'Anexo incluído',
        'attachment_removed' => 'Anexo removido',
        'ai_reviewed' => 'Revisão automática pelo Claude',
    ];

    protected $fillable = [
        'signature_contract_id',
        'event',
        'actor_id',
        'actor_name',
        'payload',
    ];

    protected $casts = [
        'signature_contract_id' => 'integer',
        'actor_id' => 'integer',
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(SignatureContract::class, 'signature_contract_id');
    }

    public function label(): string
    {
        return self::LABELS[$this->event] ?? $this->event;
    }
}
