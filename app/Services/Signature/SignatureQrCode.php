<?php

namespace App\Services\Signature;

use BaconQrCode\Encoder\Encoder;
use BaconQrCode\Common\ErrorCorrectionLevel;

/**
 * Gera o QR Code de VALIDAÇÃO, aquele que vai impresso na página de manifesto
 * do PDF final.
 *
 * Não confundir com o QR de LIBERAÇÃO, que o atendente mostra na tela: aquele
 * carrega um token de uso único, é desenhado no navegador e morre em minutos.
 * Este aponta para a página pública `/validar/{codigo}`, é público por
 * natureza e dura o que durar o papel.
 *
 * Sai como SVG em data URI porque o destino é o dompdf: SVG inline não depende
 * da extensão GD, não perde definição na impressão e não precisa de arquivo
 * temporário em disco.
 *
 * A biblioteca (bacon/bacon-qr-code) é PHP puro e não faz requisição nenhuma —
 * o QR do manifesto é gerado dentro do job, sem internet.
 */
class SignatureQrCode
{
    /**
     * O QR de uma URL, como SVG pronto para embutir no HTML do PDF.
     *
     * @param  int  $size  lado do quadrado, em pixels do PDF
     */
    public function svg(string $content, int $size = 120): string
    {
        /*
         | Correção de erro média: o manifesto é impresso e às vezes
         | fotografado torto. Alta engordaria o código sem necessidade — a URL
         | de validação é curta.
         */
        $matrix = Encoder::encode($content, ErrorCorrectionLevel::M())->getMatrix();

        $lado = $matrix->getWidth();
        $modulo = $size / ($lado + 2);

        $blocos = '';

        for ($y = 0; $y < $lado; $y++) {
            for ($x = 0; $x < $lado; $x++) {
                if ($matrix->get($x, $y) !== 1) {
                    continue;
                }

                // +1 de margem (quiet zone): sem ela, leitor nenhum acha o
                // código quando ele encosta na borda do bloco impresso.
                $blocos .= sprintf(
                    '<rect x="%s" y="%s" width="%s" height="%s"/>',
                    round(($x + 1) * $modulo, 2),
                    round(($y + 1) * $modulo, 2),
                    round($modulo, 2) + 0.05,
                    round($modulo, 2) + 0.05,
                );
            }
        }

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d">'
            . '<rect width="%d" height="%d" fill="#fff"/><g fill="#000">%s</g></svg>',
            $size, $size, $size, $size, $size, $size, $blocos,
        );
    }

    /** O mesmo SVG, embutível em `src` de imagem. */
    public function dataUri(string $content, int $size = 120): string
    {
        return 'data:image/svg+xml;base64,' . base64_encode($this->svg($content, $size));
    }
}
