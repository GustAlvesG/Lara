<?php

namespace App\Exceptions;

use Exception;

/**
 * Problema em uma linha específica da planilha (CPF inexistente, data
 * ilegível, registro repetido). As demais linhas continuam sendo conferidas.
 */
class ImportRowException extends Exception
{
}
