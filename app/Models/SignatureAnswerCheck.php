<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma ação do atendente na conferência das respostas do tablet — ver
 * SignatureAnswerCheckService.
 *
 * A aprovação vale para o `document_sha256` dela: resposta mudada depois é
 * outro original, e a aprovação deixa de valer sem ninguém precisar apagá-la.
 * Só inserção.
 *
 * @property int $id
 * @property string $action
 * @property ?string $document_sha256
 * @property ?string $note
 * @property ?array $field_keys
 * @property ?string $user_name
 */
class SignatureAnswerCheck extends Model
{
    public const ACTION_APPROVED = 'approved';
    public const ACTION_RETURNED = 'returned';
    public const ACTION_CORRECTED = 'corrected';

    public const ACTION_LABELS = [
        self::ACTION_APPROVED => 'Respostas aprovadas',
        self::ACTION_RETURNED => 'Devolvido para correção',
        self::ACTION_CORRECTED => 'Respostas corrigidas pelo atendente',
    ];

    protected $fillable = [
        'signature_document_id',
        'signature_request_id',
        'action',
        'document_sha256',
        'note',
        'field_keys',
        'user_id',
        'user_name',
    ];

    protected $casts = [
        'signature_document_id' => 'integer',
        'signature_request_id' => 'integer',
        'field_keys' => 'array',
        'user_id' => 'integer',
    ];

    /**
     * @return BelongsTo<SignatureDocument, SignatureAnswerCheck>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(SignatureDocument::class, 'signature_document_id');
    }
}
