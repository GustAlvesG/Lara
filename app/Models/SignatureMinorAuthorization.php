<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Um termo de menores assinado (ou em curso): um documento por menor.
 *
 * O documento é um SignatureDocument comum — manifesto, trilha, validação
 * pública, via e FTP vêm de lá. Esta linha só diz a que termo (evento) ele
 * pertence e quem são as pessoas, com os ids do MultiClubes e o nome como
 * estava no dia.
 *
 * "Autorizado" = documento assinado ou finalizado. Um termo abandonado no meio
 * (recusado, expirado, cancelado) não conta.
 *
 * @property int $id
 * @property string $minor_name
 * @property Carbon $minor_birth_date
 * @property string $responsible_name
 */
class SignatureMinorAuthorization extends Model
{
    /** Estados do documento em que o menor está autorizado. */
    public const AUTHORIZED_STATUSES = [
        SignatureDocument::STATUS_SIGNED,
        SignatureDocument::STATUS_FINALIZED,
    ];

    protected $fillable = [
        'signature_minor_term_id',
        'signature_document_id',
        'signature_kiosk_device_id',
        'title_code',
        'minor_member_id',
        'minor_name',
        'minor_birth_date',
        'responsible_member_id',
        'responsible_name',
    ];

    protected $casts = [
        'signature_minor_term_id' => 'integer',
        'signature_document_id' => 'integer',
        'signature_kiosk_device_id' => 'integer',
        'minor_member_id' => 'integer',
        'minor_birth_date' => 'date',
        'responsible_member_id' => 'integer',
    ];

    /**
     * @return BelongsTo<SignatureMinorTerm, SignatureMinorAuthorization>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(SignatureMinorTerm::class, 'signature_minor_term_id');
    }

    /**
     * @return BelongsTo<SignatureDocument, SignatureMinorAuthorization>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(SignatureDocument::class, 'signature_document_id');
    }

    /**
     * Só as que valem como autorização.
     *
     * @param  Builder<SignatureMinorAuthorization>  $query
     */
    public function scopeAuthorized(Builder $query): void
    {
        $query->whereHas('document', fn($q) => $q->whereIn('status', self::AUTHORIZED_STATUSES));
    }

    public function isAuthorized(): bool
    {
        return in_array($this->document?->status, self::AUTHORIZED_STATUSES, true);
    }

    /** Idade do menor num dia — por padrão, o da assinatura. */
    public function minorAgeOn(?Carbon $day = null): int
    {
        $dia = $day ?? $this->signedAt() ?? $this->created_at ?? now();

        return (int) $this->minor_birth_date->diffInYears($dia);
    }

    /** Hora da assinatura do responsável (servidor). */
    public function signedAt(): ?Carbon
    {
        return $this->document?->signers->first()?->signed_at;
    }
}
