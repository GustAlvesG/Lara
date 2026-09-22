<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * O que prova que aquela pessoa assinou aquele conteúdo: traço, foto, tempo de
 * leitura, aceite, IP e hora do servidor.
 *
 * Gravada na mesma transação da assinatura. Os caminhos apontam para o disco
 * PRIVADO; nenhum deles vira URL.
 *
 * @property int $id
 * @property ?array $strokes
 */
class SignatureEvidence extends Model
{
    use HasFactory;

    protected $fillable = [
        'signature_signer_id',
        'signature_path',
        'strokes',
        'photo_path',
        'ip',
        'user_agent',
        'read_seconds',
        'scrolled_to_end',
        'accepted',
        'viewport',
        'server_signed_at',
    ];

    protected $casts = [
        'signature_signer_id' => 'integer',
        'strokes' => 'array',
        'read_seconds' => 'integer',
        'scrolled_to_end' => 'boolean',
        'accepted' => 'boolean',
        'viewport' => 'array',
        'server_signed_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<SignatureSigner, SignatureEvidence>
     */
    public function signer(): BelongsTo
    {
        return $this->belongsTo(SignatureSigner::class, 'signature_signer_id');
    }

    /**
     * Quantos pontos o traço tem. É por aqui que se recusa a "assinatura" de
     * um toque só — ver SignatureCaptureService.
     */
    public function strokePoints(): int
    {
        return collect($this->strokes ?? [])
            ->sum(fn($stroke) => is_array($stroke) ? count($stroke) : 0);
    }
}
