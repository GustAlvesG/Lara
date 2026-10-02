<?php

namespace App\Exceptions;

use Exception;

/**
 * Lançada quando se tenta mover um documento, um signatário ou uma solicitação
 * de assinatura para um estado que não sai do estado atual.
 *
 * É erro de programação, não de operação: a tela só oferece o que a máquina de
 * estados permite. Chegar aqui quer dizer que alguém mudou o status por um
 * caminho que passa por fora dela — e é justamente isso que não pode
 * acontecer, porque esse caminho também passaria por fora da auditoria.
 */
class InvalidSignatureTransitionException extends Exception
{
}
