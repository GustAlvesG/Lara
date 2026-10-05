<?php

namespace App\Exceptions;

use Exception;

/**
 * As respostas do formulário do tablet não passaram na conferência.
 *
 * Carrega o erro de CADA campo, pela chave, porque a tela marca a pergunta
 * certa — "confira os campos" sem dizer quais faria a pessoa caçar o erro num
 * tablet de balcão, com fila atrás.
 */
class SignatureFormException extends Exception
{
    /**
     * @param  array<string, string>  $errors  chave do campo => mensagem
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('Confira as respostas destacadas.');
    }
}
