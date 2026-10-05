<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * O PDF enviado como documento pronto não pôde ser lido pelo sistema.
 *
 * A mensagem é escrita para quem enviou o arquivo — vai direto para a tela, e
 * diz o que fazer com ele.
 */
class UnreadablePdfException extends RuntimeException
{
}
