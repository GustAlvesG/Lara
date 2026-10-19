<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Recusa da baixa manual de pagamento (ManualPaymentService). A mensagem é
 * de tela: vai direto para o operador.
 */
class ManualPaymentException extends RuntimeException
{
}
