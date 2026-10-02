<?php

namespace App\Services\PoliBot;

/**
 * Tira do texto o que não deve ficar gravado em claro no histórico do bot:
 * CPF (com ou sem máscara) e datas completas. O valor de verdade, quando o
 * fluxo precisa dele, vive só em bot_sessions.data durante a conversa.
 */
class PoliTextMask
{
    public static function mask(?string $texto): ?string
    {
        if ($texto === null) {
            return null;
        }

        // CPF: 000.000.000-00, 00000000000 e variações de separador.
        $texto = preg_replace('/(?<!\d)\d{3}[.\s]?\d{3}[.\s]?\d{3}[-.\s]?\d{2}(?!\d)/', '***.***.***-**', $texto);

        // Datas completas: 25/09/1980, 25-09-80, 25.09.1980.
        return preg_replace('/(?<!\d)\d{1,2}[\/.\-]\d{1,2}[\/.\-]\d{2,4}(?!\d)/', '**/**/****', $texto);
    }
}
