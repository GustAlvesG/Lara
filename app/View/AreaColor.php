<?php

namespace App\View;

/**
 * As cores de área do clube (capa + tinta), definidas como tokens em
 * resources/css/app.css (`--a-portaria`, `--a-portaria-ink`...).
 *
 * Os componentes pintam a área por estilo inline com esses tokens, e não por
 * classe montada (`bg-area-{$area}`): classe montada por variável não é vista
 * pelo build do Tailwind e sumiria do CSS.
 */
final class AreaColor
{
    public const AREAS = ['portaria', 'reservas', 'externos', 'freela', 'placar', 'lara', 'info', 'compras', 'cartao', 'inicio'];

    public const DEFAULT = 'cartao';

    public static function normalize(?string $area): string
    {
        return in_array($area, self::AREAS, true) ? $area : self::DEFAULT;
    }

    /**
     * Estilo inline com `--c` (fundo) e `--ci` (tinta), para filhos que usam
     * `bg-[rgb(var(--c))]`, e o fundo/tinta já aplicados no próprio elemento.
     */
    public static function style(?string $area, bool $paint = true): string
    {
        $area = self::normalize($area);
        $vars = "--c: var(--a-{$area}); --ci: var(--a-{$area}-ink);";

        return $paint ? $vars . ' background-color: rgb(var(--c)); color: rgb(var(--ci));' : $vars;
    }
}
