<?php

namespace App\Exceptions;

use Exception;

/**
 * Recusa de uma liberação por QR Code ou de uma sessão do tablet.
 *
 * Carrega o status HTTP porque o tablet trata cada caso de um jeito: um QR
 * expirado pede outro ao atendente (410), um QR relido avisa que aquele já foi
 * usado (409), e uma sessão vencida volta à tela de espera (419).
 *
 * As mensagens são escritas para aparecer no tablet, para quem está no balcão
 * — e nunca dizem por que um token não existe. "QR Code inválido" é a mesma
 * resposta para token forjado e para token de outro clube: distinguir os dois
 * ensinaria a adivinhar.
 */
class SignatureSessionException extends Exception
{
    public function __construct(string $message, public readonly int $status = 409)
    {
        parent::__construct($message);
    }

    public static function invalidToken(): self
    {
        return new self('QR Code inválido. Peça ao atendente que gere outro.', 404);
    }

    public static function expired(): self
    {
        return new self('QR Code expirado. Peça ao atendente que gere outro.', 410);
    }

    public static function alreadyUsed(): self
    {
        return new self('Este QR Code já foi usado. Peça ao atendente que gere outro.', 409);
    }

    public static function superseded(): self
    {
        return new self('Este QR Code foi substituído por outro. Leia o QR que está na tela do atendente.', 410);
    }

    public static function canceled(): self
    {
        return new self('Este atendimento foi cancelado pelo atendente.', 410);
    }

    public static function outsideNetwork(): self
    {
        return new self('Este tablet não está na rede autorizada para assinatura.', 403);
    }

    public static function sessionExpired(): self
    {
        return new self('A sessão expirou. Peça ao atendente que gere um novo QR Code.', 419);
    }
}
