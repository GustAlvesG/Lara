<?php

namespace App\Exceptions;

use Exception;

/**
 * Lançada quando a liberação que exige o coordenador do setor Comercial não é
 * autorizada: matrícula inexistente/inativa, usuário que não é coordenador
 * daquele setor, coordenador sem PIN ou PIN errado.
 */
class CoordinatorAuthorizationException extends Exception
{
    /**
     * Onde a tela deve voltar a pedir o dado: 'matricula' quando o problema é
     * quem foi informado, 'pin' quando a matrícula está certa e só o PIN falhou.
     */
    public function __construct(string $message, public readonly string $step = 'matricula')
    {
        parent::__construct($message);
    }
}
