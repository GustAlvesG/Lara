<?php

namespace App\Services\Signature;

use App\Models\SignatureDocument;

/**
 * Para onde apontam o QR Code e o link de validação impressos no PDF.
 *
 * O Lara não é acessível de fora da rede do clube: o `/validar/{codigo}`
 * impresso num documento que vai para a casa da pessoa não abre. Com o lacre
 * ligado (SignaturePdfSealer), o PDF final leva a assinatura digital do clube,
 * e o validador oficial do governo (validar.iti.gov.br) o confere de qualquer
 * lugar — então o PDF final aponta para lá.
 *
 * O PDF ORIGINAL (o que a pessoa lê no tablet, antes de assinar) não é
 * lacrado: no ITI ele daria "sem assinatura". Ele continua apontando para o
 * `/validar` do Lara, que é onde esse hash é conferido.
 *
 * O documento assinado pelo gov.br já traz as assinaturas das pessoas: o ITI
 * o confere mesmo sem o lacre.
 */
class SignatureValidationLink
{
    public const ITI_URL = 'https://validar.iti.gov.br';

    public function __construct(private SignaturePdfSealer $sealer)
    {
    }

    /** O PDF final deste documento é conferido no validador oficial? */
    public function finalUsesIti(SignatureDocument $document): bool
    {
        return $this->sealer->isEnabled() || $document->govbr_check_id !== null;
    }

    /** Endereço impresso no PDF final (QR do manifesto e do relatório, rodapé). */
    public function forFinal(SignatureDocument $document): string
    {
        return $this->finalUsesIti($document) ? self::ITI_URL : $this->internal($document);
    }

    /** A página de validação do próprio Lara (só abre dentro da rede do clube). */
    public function internal(SignatureDocument $document): string
    {
        return url('/validar/' . $document->validation_code);
    }
}
