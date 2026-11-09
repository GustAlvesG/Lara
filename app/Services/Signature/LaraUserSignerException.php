<?php

namespace App\Services\Signature;

/**
 * Usuário do Lara que não pode ser escolhido como signatário (inexistente,
 * inativo ou sem CPF válido). A mensagem vai para a tela do atendente.
 */
class LaraUserSignerException extends \RuntimeException
{
}
