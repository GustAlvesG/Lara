<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Convite por e-mail para assinar pelo gov.br: o registro de que o PDF foi
 * enviado, para quem e por quem. Ver GovbrInviteService.
 *
 * @property int $id
 * @property string $email
 */
class SignatureGovbrInvite extends Model
{
    protected $fillable = [
        'signature_signer_id',
        'email',
        'sent_by',
        'sent_by_name',
    ];

    protected $casts = [
        'signature_signer_id' => 'integer',
        'sent_by' => 'integer',
    ];

    /**
     * @return BelongsTo<SignatureSigner, SignatureGovbrInvite>
     */
    public function signer(): BelongsTo
    {
        return $this->belongsTo(SignatureSigner::class, 'signature_signer_id');
    }

    /** E-mail mascarado para tela e trilha: m***a@exemplo.com.br. */
    public function maskedEmail(): string
    {
        return self::mask($this->email);
    }

    public static function mask(string $email): string
    {
        return \App\Support\EmailMask::of($email);
    }
}
