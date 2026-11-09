<?php

namespace App\Support;

/**
 * Telefone do usuário do Lara (09/11/2026): gravado só com dígitos — DDD +
 * número (10 ou 11 dígitos), com ou sem o 55 do país na frente. É para onde
 * vão os avisos de WhatsApp e o contato do signatário "usuário do Lara".
 */
final class Phone
{
    public static function digits(?string $value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?? '';
    }

    public static function isValid(?string $value): bool
    {
        $digitos = self::digits($value);

        if (strlen($digitos) >= 12 && str_starts_with($digitos, '55')) {
            $digitos = substr($digitos, 2);
        }

        return in_array(strlen($digitos), [10, 11], true) && $digitos[0] !== '0';
    }

    /** "(11) 98765-4321" — o que a tela mostra. Fora do padrão, devolve como está. */
    public static function format(?string $value): string
    {
        $digitos = self::digits($value);
        $pais = '';

        if (strlen($digitos) >= 12 && str_starts_with($digitos, '55')) {
            $pais = '+55 ';
            $digitos = substr($digitos, 2);
        }

        return match (strlen($digitos)) {
            11 => $pais . sprintf('(%s) %s-%s', substr($digitos, 0, 2), substr($digitos, 2, 5), substr($digitos, 7)),
            10 => $pais . sprintf('(%s) %s-%s', substr($digitos, 0, 2), substr($digitos, 2, 4), substr($digitos, 6)),
            default => (string) $value,
        };
    }
}
