<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Falha ao falar com a API do Claude, já com a mensagem para a tela (em
 * português, sem detalhe técnico). O detalhe vai para o log.
 */
class ClaudeException extends RuntimeException
{
}
