<?php

namespace App\Support\Replay;

/**
 * As duas orientações de vídeo do Replay e a tela em que o overlay é
 * composto.
 *
 * A resolução da câmera NÃO é configurada no Lara — é decisão de quem
 * instalou o equipamento. Mas o overlay precisa nascer em algum tamanho, e
 * escolhemos 1080p no lado maior: é o denominador comum das câmeras
 * instaladas e mantém o arquivo leve. A API entrega `width`/`height` junto
 * com a URL justamente para o sistema de captura reescalar o PNG se a câmera
 * dele rodar em outra resolução — a proporção é a mesma, então o reescalo é
 * exato, sem distorção.
 */
final class Orientation
{
    const VERTICAL = 'vertical';
    const HORIZONTAL = 'horizontal';

    const ALL = [self::VERTICAL, self::HORIZONTAL];

    /** Duração do clipe: sempre os segundos ANTERIORES ao aperto do botão. */
    const MIN_CLIP_SECONDS = 5;
    const MAX_CLIP_SECONDS = 60;
    const DEFAULT_CLIP_SECONDS = 30;

    const DEFAULT_ORIENTATION = self::HORIZONTAL;

    /** Tela de composição do overlay, em pixels. */
    const DIMENSIONS = [
        self::VERTICAL => ['width' => 1080, 'height' => 1920],
        self::HORIZONTAL => ['width' => 1920, 'height' => 1080],
    ];

    const LABELS = [
        self::VERTICAL => 'Vertical (9:16)',
        self::HORIZONTAL => 'Horizontal (16:9)',
    ];

    public static function valid(?string $orientation): bool
    {
        return in_array($orientation, self::ALL, true);
    }

    public static function normalize(?string $orientation): string
    {
        return self::valid($orientation) ? $orientation : self::DEFAULT_ORIENTATION;
    }

    /** @return array{width: int, height: int} */
    public static function dimensions(?string $orientation): array
    {
        return self::DIMENSIONS[self::normalize($orientation)];
    }

    public static function label(?string $orientation): string
    {
        return self::LABELS[self::normalize($orientation)];
    }
}
