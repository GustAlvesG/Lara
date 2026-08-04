<?php

namespace App\Exceptions\Sicoob;

use Throwable;

/**
 * O banco recebeu e recusou o pagamento (estado REJEITADO / NÃO_REALIZADO, ou
 * um 400 com `violacoes` na confirmação).
 *
 * Recusa é resposta definitiva e NEGATIVA: a conta não foi debitada. Por isso
 * um pagamento rejeitado pode ser refeito — ao contrário do `unknown`, em que
 * não se sabe o desfecho.
 */
class SicoobPaymentRejectedException extends SicoobException
{
    /** @param array<string, mixed> $context */
    public function __construct(
        string $message,
        public readonly ?string $detalheRejeicao = null,
        array $context = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $context, $previous);
    }

    public function userMessage(): string
    {
        return $this->detalheRejeicao
            ? 'O banco recusou o Pix: ' . $this->detalheRejeicao . ' Nenhum valor foi transferido.'
            : 'O banco recusou o Pix. Nenhum valor foi transferido.';
    }
}
