<?php

namespace App\Exceptions;

use Exception;

/**
 * Lançada quando uma operação de lote não cabe no estado atual dele — enviar um
 * rascunho vazio, mexer num lote já enviado, analisar o que a gerência já
 * analisou.
 */
class FreelancerBatchException extends Exception
{
}
