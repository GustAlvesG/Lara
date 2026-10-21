<?php

namespace App\Services\Signature\Directors;

use RuntimeException;

/**
 * Recusa de uma regra da Assinatura Diretoria — mandato, escolha do diretor,
 * entrada no tablet. A mensagem é para a tela; `status` é o HTTP do tablet.
 */
class DirectorException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 422)
    {
        parent::__construct($message);
    }
}
