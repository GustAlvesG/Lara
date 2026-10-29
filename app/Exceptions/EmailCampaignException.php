<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Recusa de uma operação do Disparo de E-mails (começar sem lista, mexer em
 * campanha que já está enviando…). A mensagem é a que vai para a tela.
 */
class EmailCampaignException extends RuntimeException
{
}
