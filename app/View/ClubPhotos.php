<?php

namespace App\View;

/**
 * As fotos do clube na faixa das telas de entrada (página inicial, entrar e
 * criar conta). Desde 11/11/2026 cada abertura sorteia uma: o sorteio é no
 * servidor, para o navegador já pedir a foto certa no <link rel="preload">
 * e a tela não piscar trocando de imagem.
 *
 * Os arquivos ficam em public/images/auth/: cópias recortadas (sem a data
 * que a câmera imprime) e comprimidas. `position` é o object-position (o
 * ponto que não pode sair do quadro na faixa estreita do celular) e `drift`
 * a direção do movimento lento da foto (x, y), em partes da largura/altura.
 */
final class ClubPhotos
{
    public const ALL = [
        'piscina' => [
            'src' => 'images/auth/clube-piscina.jpg',
            'alt' => 'Piscina do Clube dos Funcionários com a torre de saltos, céu azul e a mata ao fundo',
            'position' => '50% 45%',
            'drift' => ['-2%', '-1%'],
        ],
        'sede' => [
            'src' => 'images/auth/clube-sede.jpg',
            'alt' => 'Fachada da sede do Clube dos Funcionários, com o símbolo do clube, as bandeiras e as palmeiras',
            'position' => '58% 50%',
            'drift' => ['2%', '-1%'],
        ],
        'aereo-quadras' => [
            'src' => 'images/auth/clube-aereo-quadras.jpg',
            'alt' => 'Vista aérea do clube: as piscinas, o toboágua, a quadra de saibro e a cobertura com placas solares',
            'position' => '55% 50%',
            'drift' => ['-2%', '1.5%'],
        ],
        'aereo-mata' => [
            'src' => 'images/auth/clube-aereo-mata.jpg',
            'alt' => 'Vista aérea do clube inteiro cercado pela mata: piscinas, quadras, campos e o ginásio',
            'position' => '50% 50%',
            'drift' => ['1.5%', '2%'],
        ],
    ];

    /**
     * @return array{key: string, src: string, alt: string, position: string, drift: array{0: string, 1: string}}
     */
    public static function random(): array
    {
        $key = array_rand(self::ALL);

        return ['key' => $key] + self::ALL[$key];
    }

    /** O estilo da <img>: o enquadramento e a direção do movimento. */
    public static function style(array $photo): string
    {
        return sprintf('object-position: %s; --drift-x: %s; --drift-y: %s', $photo['position'], ...$photo['drift']);
    }
}
