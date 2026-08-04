<?php

namespace App\Exceptions\Sicoob;

/**
 * Saldo insuficiente na conta pagadora.
 *
 * Chega por dois caminhos: a pré-checagem em `SicoobContaCorrenteService`
 * (antes de qualquer coisa sair) ou a recusa do próprio banco na confirmação.
 * Nos dois casos nada foi transferido, e a tentativa pode ser refeita depois
 * de a conta ter saldo.
 */
class SicoobInsufficientFundsException extends SicoobException
{
    public function userMessage(): string
    {
        return 'Saldo insuficiente na conta para este Pix. Nenhum valor foi transferido; refaça a baixa depois de recompor o saldo.';
    }
}
