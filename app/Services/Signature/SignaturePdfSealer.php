<?php

namespace App\Services\Signature;

use App\Models\SignatureDocument;
use RuntimeException;

/**
 * Lacre criptográfico do PDF final com o certificado A1 do clube (PAdES).
 *
 * **Não implementado nesta entrega, de propósito.** O que dá valor jurídico à
 * assinatura da pessoa aqui são as evidências (Lei 14.063/2020, assinatura
 * eletrônica avançada): identidade conferida, aceite explícito, traço, foto,
 * hora do servidor e trilha encadeada. O certificado do clube não assinaria
 * pela pessoa — lacraria o ARQUIVO, provando que ele não mudou depois de
 * emitido, que é outra coisa e um degrau a mais.
 *
 * Esta classe existe para que esse degrau caiba depois sem reescrever o job:
 * é o único ponto por onde os bytes finais passam antes de ir para o disco.
 * É também onde entraria um carimbo cirúrgico via FPDI, no dia em que o PDF
 * final precisar ser o original carimbado em vez de re-renderizado.
 *
 * Ligar `signature.pades.enabled` sem implementar falha alto, na hora: um
 * lacre que silenciosamente não acontece é pior que lacre nenhum, porque
 * alguém vai passar a contar com ele.
 */
class SignaturePdfSealer
{
    public function isEnabled(): bool
    {
        return (bool) config('signature.pades.enabled', false);
    }

    /**
     * Devolve os bytes a gravar.
     *
     * @throws RuntimeException  quando o lacre está ligado mas não implementado
     */
    public function seal(string $pdf, SignatureDocument $document): string
    {
        if (!$this->isEnabled()) {
            return $pdf;
        }

        throw new RuntimeException(
            'Lacre PAdES está ligado (SIGNATURE_PADES_ENABLED), mas ainda não foi implementado. '
            . 'Desligue a flag ou implemente App\Services\Signature\SignaturePdfSealer::seal().'
        );
    }
}
