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

    /**
     * O Eloquent pluraliza "Evidence" como "evidence" (substantivo incontável
     * em inglês) e procuraria `signature_evidence`. A tabela é
     * `signature_evidences`, no plural regular das demais do módulo.
     */
    protected $table = 'signature_evidences';

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
     * um toque só — ver SignatureCaptureService, que chama o mesmo método
     * ANTES de gravar.
     *
     * A contagem mora aqui, e não nos dois lugares, porque já estava escrita
     * duas vezes com regras diferentes: uma entendia `[{points: [...]}]` e a
     * outra contava os traços em vez dos pontos. O teste flagrou 1 onde eram
     * 60 — e, num traço de verdade, as duas contas aprovariam a assinatura,
     * então a divergência só apareceria no dia em que importasse.
     */
    public function strokePoints(): int
    {
        return self::countPoints($this->strokes);
    }

    /**
     * Aceita as duas formas que o tablet pode mandar: uma lista de traços, e
     * cada traço como `{points: [...]}` ou como a própria lista de pontos.
     *
     * @param  array<mixed>|null  $strokes
     */
    public static function countPoints(?array $strokes): int
    {
        $total = 0;

        foreach ($strokes ?? [] as $stroke) {
            if (!is_array($stroke)) {
                continue;
            }

            $total += isset($stroke['points']) && is_array($stroke['points'])
                ? count($stroke['points'])
                : count($stroke);
        }

        return $total;
    }
}
