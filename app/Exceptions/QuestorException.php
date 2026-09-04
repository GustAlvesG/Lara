<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Falha na integração com o Questor (ERP de compras) — conexão indisponível,
 * módulo desligado, ordem inexistente ou gravação bloqueada.
 *
 * A mensagem é escrita para ser mostrada ao usuário na tela: quem vê isso é
 * quem ia aprovar uma ordem de compra, não o desenvolvedor.
 */
class QuestorException extends RuntimeException
{
}
