<?php

namespace App\Exceptions;

use Exception;

/**
 * Falha que invalida a planilha inteira (arquivo ilegível, cabeçalho errado,
 * nenhuma linha de dados) — nada é importado.
 */
class SpreadsheetImportException extends Exception
{
}
