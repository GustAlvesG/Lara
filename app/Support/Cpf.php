<?php

namespace App\Support;

/**
 * CPF: normalização, máscara para exibição e máscara para REGISTRO.
 *
 * As duas máscaras são coisas diferentes e não devem ser confundidas:
 * `format()` é o CPF inteiro, bonito, para quem tem permissão de ver o cadastro;
 * `mask()` esconde o miolo e é o que vai para log, auditoria, manifesto e
 * página pública de validação.
 *
 * `digits()` existe porque o mesmo CPF aparece com e sem pontuação nos
 * cadastros deste sistema (a tabela `employees` tem os dois formatos), e
 * comparar texto cru já deixou passar gente que estava cadastrada.
 */
class Cpf
{
    /** Só os dígitos — a forma canônica para comparar e gravar. */
    public static function digits(?string $cpf): string
    {
        return preg_replace('/\D/', '', (string) $cpf) ?? '';
    }

    /** 123.456.789-00 — para quem pode ver o cadastro. */
    public static function format(?string $cpf): string
    {
        $digits = self::digits($cpf);

        if (strlen($digits) !== 11) {
            return $digits;
        }

        return substr($digits, 0, 3) . '.' . substr($digits, 3, 3) . '.'
            . substr($digits, 6, 3) . '-' . substr($digits, 9, 2);
    }

    /**
     * 123.***.**9-00 — a forma que pode aparecer em log, auditoria, manifesto
     * e página pública. Mantém as pontas porque é por elas que uma pessoa
     * reconhece o próprio CPF sem que o número seja revelado.
     */
    public static function mask(?string $cpf): string
    {
        $digits = self::digits($cpf);

        if ($digits === '') {
            return '';
        }

        if (strlen($digits) !== 11) {
            // Documento fora do padrão: esconde tudo menos as duas pontas.
            return strlen($digits) <= 4
                ? str_repeat('*', strlen($digits))
                : substr($digits, 0, 2) . str_repeat('*', strlen($digits) - 4) . substr($digits, -2);
        }

        return substr($digits, 0, 3) . '.***.**' . substr($digits, 8, 1) . '-' . substr($digits, 9, 2);
    }

    /**
     * Confere o que a pessoa digitou no tablet contra o CPF do cadastro.
     *
     * `partial` pede os quatro primeiros dígitos — o suficiente para distinguir
     * duas pessoas na fila do balcão sem que o atendente, olhando de lado, veja
     * o CPF inteiro sendo digitado.
     *
     * A comparação é feita com hash_equals: são dígitos, não senha, mas o
     * caminho do "errou no primeiro dígito" não precisa ser mais rápido que o
     * do "errou no último".
     *
     * @param  'partial'|'full'|'none'  $mode
     */
    public static function matches(?string $typed, ?string $stored, string $mode): bool
    {
        if ($mode === 'none') {
            return true;
        }

        $typedDigits = self::digits($typed);
        $storedDigits = self::digits($stored);

        if ($storedDigits === '' || $typedDigits === '') {
            return false;
        }

        if ($mode === 'partial') {
            return strlen($typedDigits) === 4
                && hash_equals(substr($storedDigits, 0, 4), $typedDigits);
        }

        return hash_equals($storedDigits, $typedDigits);
    }
}
