<?php

namespace App\Services\Replay;

use RuntimeException;

/**
 * O servidor do rePET recusou ou não respondeu. A mensagem já vem pronta para
 * a tela (em português, sem token nem URL); `status` é o HTTP devolvido, ou
 * null quando nem houve resposta (rede, timeout, reinício das 06:00).
 *
 * `uncertain` = a chamada pode ter sido aplicada mesmo sem resposta (timeout
 * depois de enviar). Para ponto, set e volume em passo isso importa: o
 * contrato pede conferir o estado antes de repetir, nunca reenviar às cegas.
 */
class RepetUnavailable extends RuntimeException
{
    public function __construct(string $message, public readonly ?int $status = null, public readonly bool $uncertain = false)
    {
        parent::__construct($message);
    }
}
