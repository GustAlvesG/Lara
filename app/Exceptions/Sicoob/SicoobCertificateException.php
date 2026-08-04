<?php

namespace App\Exceptions\Sicoob;

/**
 * Problema no certificado ICP Brasil: arquivo ausente, ilegível pelo usuário
 * do PHP, senha da chave errada, par certificado/chave que não casa, ou
 * certificado vencido (o handshake mTLS morre antes do HTTP).
 *
 * Nada saiu da conta — sem handshake não há requisição.
 */
class SicoobCertificateException extends SicoobException
{
    public function userMessage(): string
    {
        return 'O certificado digital da integração não pôde ser usado. Nenhum valor foi transferido. Avise a TI.';
    }
}
