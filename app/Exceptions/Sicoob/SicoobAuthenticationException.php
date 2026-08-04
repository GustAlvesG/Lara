<?php

namespace App\Exceptions\Sicoob;

/**
 * O Sicoob recusou as credenciais: client_id inválido, app inativo no portal,
 * escopo não habilitado ou token recusado pela API.
 *
 * Nada saiu da conta — a falha acontece antes de qualquer movimentação.
 */
class SicoobAuthenticationException extends SicoobException
{
    public function userMessage(): string
    {
        return 'O banco recusou as credenciais da integração. Nenhum valor foi transferido. Avise a TI.';
    }
}
