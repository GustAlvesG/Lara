<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Observação da Gerência ao devolver um contrato — de uma cláusula
 * (`clause_uid`) ou geral (nulo). Quem redige marca como resolvida.
 */
class SignatureContractComment extends Model
{
    protected $fillable = [
        'signature_contract_id',
        'signature_contract_version_id',
        'clause_uid',
        'body',
        'author_id',
        'author_name',
        'resolved_at',
        'resolved_by_name',
    ];

    protected $casts = [
        'signature_contract_id' => 'integer',
        'signature_contract_version_id' => 'integer',
        'author_id' => 'integer',
        'resolved_at' => 'datetime',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(SignatureContract::class, 'signature_contract_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(SignatureContractVersion::class, 'signature_contract_version_id');
    }

    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }
}
