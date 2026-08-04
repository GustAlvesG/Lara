<?php

namespace App\Exceptions\Sicoob;

/**
 * A confirmação foi enviada e a resposta se perdeu — timeout, conexão cortada
 * ou 5xx depois de o corpo ter subido.
 *
 * É o caso mais perigoso do fluxo, e o único que não admite retry automático:
 * o Pix PODE ter sido processado. Quem responde é a consulta
 * `GET /pagamentos/{endToEndId}`, feita pela reconciliação — nunca um reenvio.
 *
 * O pagamento fica em `unknown` até o banco dizer o que aconteceu.
 */
class SicoobPaymentOutcomeUnknownException extends SicoobException
{
    public function userMessage(): string
    {
        return 'A resposta do banco não chegou e o Pix pode ter sido processado. '
            . 'NÃO refaça a baixa: o sistema vai consultar o banco e atualizar o contrato sozinho.';
    }
}
