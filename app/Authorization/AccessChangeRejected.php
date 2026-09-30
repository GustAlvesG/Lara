<?php

namespace App\Authorization;

use RuntimeException;

/**
 * Mudança de acesso recusada por uma das travas do AccessManager. A mensagem
 * é para a tela: diz o que não pôde ser feito e por quê.
 */
class AccessChangeRejected extends RuntimeException
{
}
