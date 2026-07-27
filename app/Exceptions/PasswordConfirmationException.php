<?php

namespace App\Exceptions;

use Exception;

/**
 * Lançada quando uma ação que exige reconfirmação de senha (feita pelo bot do
 * Telegram, que não mantém sessão) não vem acompanhada de credenciais válidas.
 */
class PasswordConfirmationException extends Exception
{
}
