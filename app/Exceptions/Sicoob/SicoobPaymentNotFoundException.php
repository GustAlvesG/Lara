<?php

namespace App\Exceptions\Sicoob;

/**
 * O Sicoob não conhece o `endToEndId` consultado (HTTP 404).
 *
 * O que isso significa depende de já termos ou não enviado a confirmação, e a
 * diferença é a de sempre — dinheiro:
 *
 *   nunca confirmamos (`initiated`) → o banco reservou o id e nada foi pago.
 *                                     Marcar `failed` e liberar nova tentativa
 *                                     é seguro.
 *   já confirmamos (`sent`/`unknown`) → 404 NÃO é prova de que não pagou. Pode
 *                                     ser latência de liquidação. O pagamento
 *                                     continua em aberto e escala para
 *                                     conferência humana.
 *
 * Quem aplica essa distinção é `SicoobPixPagamentoService::reconciliar()`.
 */
class SicoobPaymentNotFoundException extends SicoobException
{
    public function userMessage(): string
    {
        return 'O banco não encontrou este pagamento. A tentativa ficou registrada para conferência.';
    }
}
