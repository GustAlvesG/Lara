<?php

namespace App\Exceptions;

use Exception;

/**
 * Lançada quando se tenta alterar, assinar ou cancelar um contrato de
 * freelancer que já está travado (assinado por alguma das partes ou cancelado).
 */
class FreelancerServiceLockedException extends Exception
{
}
