<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Anexo do contrato (a proposta, um projeto). Fica com o contrato durante a
 * redação e a validação e, na aprovação, é copiado para o documento de
 * assinatura — segue junto para quem assina.
 */
class SignatureContractAttachment extends Model
{
    protected $fillable = [
        'signature_contract_id',
        'label',
        'original_name',
        'path',
        'mime',
        'bytes',
        'sha256',
        'uploaded_by',
        'uploaded_by_name',
    ];

    protected $casts = [
        'signature_contract_id' => 'integer',
        'bytes' => 'integer',
        'uploaded_by' => 'integer',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(SignatureContract::class, 'signature_contract_id');
    }
}
