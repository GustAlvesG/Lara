<?php

namespace App\Exceptions;

use Exception;

/**
 * O cachê não está no ponto para a ação pedida — lote já enviado, item já
 * assinado, reconferência fora de ordem. A mensagem é a mesma que a tela
 * mostra: o motivo da recusa não deve ser reescrito em cada lugar.
 */
class EmployeeCacheException extends Exception
{
}
