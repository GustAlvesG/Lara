<?php

namespace App\Exceptions;

use Exception;

/**
 * Lançada quando se tenta editar, congelar ou cancelar um documento de
 * assinatura num estado que não permite o ato — congelado, já assinado,
 * cancelado, sem signatário ou com dado obrigatório faltando.
 *
 * Diferente de InvalidSignatureTransitionException, esta é ERRO DE OPERAÇÃO, e
 * não de programação: a mensagem é escrita para o atendente ler na tela, do
 * mesmo jeito que FreelancerServiceLockedException.
 */
class SignatureDocumentLockedException extends Exception
{
}
