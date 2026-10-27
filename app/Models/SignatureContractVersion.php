<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Uma versão do contrato: o retrato do que foi enviado à Gerência. Nunca se
 * edita — é contra ela que a Gerência compara o envio seguinte ("o que mudou
 * desde que eu devolvi").
 */
class SignatureContractVersion extends Model
{
    public const DECISION_APPROVED = 'approved';
    public const DECISION_RETURNED = 'returned';
    /** A Gerência ajustou o texto na validação: a decisão foi na versão seguinte. */
    public const DECISION_SUPERSEDED = 'superseded';

    protected $fillable = [
        'signature_contract_id',
        'version',
        'content',
        'submitted_by',
        'submitted_by_name',
        'submitted_at',
        'note',
        'by_reviewer',
        'decision',
        'decided_by',
        'decided_by_name',
        'decided_at',
        'decision_note',
    ];

    protected $casts = [
        'signature_contract_id' => 'integer',
        'version' => 'integer',
        'content' => 'array',
        'submitted_by' => 'integer',
        'submitted_at' => 'datetime',
        'by_reviewer' => 'boolean',
        'decided_by' => 'integer',
        'decided_at' => 'datetime',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(SignatureContract::class, 'signature_contract_id');
    }

    /** @return HasMany<SignatureContractComment> */
    public function comments(): HasMany
    {
        return $this->hasMany(SignatureContractComment::class)->orderBy('id');
    }

    public function decisionLabel(): string
    {
        return match ($this->decision) {
            self::DECISION_APPROVED => 'Aprovada',
            self::DECISION_RETURNED => 'Devolvida',
            self::DECISION_SUPERSEDED => 'Ajustada pela Gerência (decisão na versão seguinte)',
            default => 'Em validação',
        };
    }
}
