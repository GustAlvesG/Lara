<?php

namespace App\Services\Signature\MinorTerms;

use RuntimeException;

/**
 * Recusa do autoatendimento, com a mensagem que vai à tela do tablet e o
 * status HTTP. `restart` = o atendimento acabou (tentativas esgotadas, prazo),
 * e o tablet volta ao começo.
 */
class MinorTermException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $status = 422,
        public readonly bool $restart = false,
    ) {
        parent::__construct($message);
    }
}
