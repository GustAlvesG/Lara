<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Recusa de uma ação sobre um contrato (Assinaturas → Contratos), com a
 * mensagem que a tela mostra. `problems` traz a lista do que falta quando a
 * recusa é "o contrato ainda não está pronto" — ver ContractRenderer::problems.
 */
class ContractException extends RuntimeException
{
    /**
     * @param  array<int, array{level: string, message: string, clause: ?string}>  $problems
     */
    public function __construct(string $message, public readonly array $problems = [])
    {
        parent::__construct($message);
    }
}
