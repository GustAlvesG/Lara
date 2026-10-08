<?php

namespace App\Support;

/**
 * E-mail mascarado para tela e trilha: m***a@exemplo.com.br.
 *
 * O domínio fica inteiro — é o que ajuda a pessoa a saber onde procurar —, e
 * do nome sobram a primeira e a última letra.
 */
class EmailMask
{
    public static function of(?string $email): string
    {
        [$usuario, $dominio] = array_pad(explode('@', (string) $email, 2), 2, '');

        $visivel = mb_strlen($usuario) <= 2
            ? mb_substr($usuario, 0, 1) . '*'
            : mb_substr($usuario, 0, 1) . str_repeat('*', 3) . mb_substr($usuario, -1);

        return $visivel . '@' . $dominio;
    }
}
