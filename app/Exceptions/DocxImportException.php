<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * O arquivo enviado como modelo não pôde ser lido como documento do Word.
 *
 * A mensagem é escrita para quem enviou o arquivo — vai direto para a tela.
 */
class DocxImportException extends RuntimeException
{
}
