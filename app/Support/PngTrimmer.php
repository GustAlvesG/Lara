<?php

namespace App\Support;

/**
 * Recorta um PNG transparente ao desenho.
 *
 * O tablet manda a tela de desenho inteira, e a assinatura (ou a rubrica)
 * ocupa um pedaço dela. Reduzida a uma caixa pequena, uma tela quase toda
 * vazia vira um risquinho ilegível — recortada, o traço preenche a caixa.
 */
class PngTrimmer
{
    /**
     * Sem GD, ou se a imagem não abrir, devolve os bytes como vieram: traço
     * pequeno é melhor que assinatura recusada.
     */
    public static function trim(string $png, int $margin = 4): string
    {
        if (!function_exists('imagecreatefromstring')) {
            return $png;
        }

        $imagem = @imagecreatefromstring($png);

        if (!$imagem) {
            return $png;
        }

        $largura = imagesx($imagem);
        $altura = imagesy($imagem);

        [$x1, $y1, $x2, $y2] = [$largura, $altura, -1, -1];

        for ($y = 0; $y < $altura; $y++) {
            for ($x = 0; $x < $largura; $x++) {
                // 127 no canal alfa é transparência total.
                if (((imagecolorat($imagem, $x, $y) >> 24) & 0x7F) < 120) {
                    $x1 = min($x1, $x);
                    $x2 = max($x2, $x);
                    $y1 = min($y1, $y);
                    $y2 = max($y2, $y);
                }
            }
        }

        if ($x2 < 0) {
            return $png;
        }

        $esquerda = max(0, $x1 - $margin);
        $topo = max(0, $y1 - $margin);

        $recorte = imagecrop($imagem, [
            'x' => $esquerda,
            'y' => $topo,
            'width' => min($largura, $x2 + $margin + 1) - $esquerda,
            'height' => min($altura, $y2 + $margin + 1) - $topo,
        ]);

        if (!$recorte) {
            return $png;
        }

        imagealphablending($recorte, false);
        imagesavealpha($recorte, true);

        ob_start();
        imagepng($recorte);

        return (string) ob_get_clean();
    }
}
