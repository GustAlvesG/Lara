<?php

namespace App\Exceptions;

/**
 * Recusa de um link de assinatura remota.
 *
 * Leva, além do status HTTP, um `reason` estável para o site externo decidir
 * a tela (link vencido, já usado, revogado…) sem interpretar a mensagem. A
 * mensagem é escrita para quem abriu o link em casa.
 */
class SignatureRemoteLinkException extends SignatureSessionException
{
    public function __construct(string $message, int $status, public readonly string $reason)
    {
        parent::__construct($message, $status);
    }

    public static function invalid(): self
    {
        return new self('Link de assinatura inválido. Confira se abriu o link inteiro.', 404, 'invalid_link');
    }

    public static function expired(): self
    {
        return new self('Este link venceu. Peça ao clube que envie um novo link de assinatura.', 410, 'link_expired');
    }

    public static function superseded(): self
    {
        return new self('Este link foi substituído por um mais recente. Use o último link que você recebeu.', 410, 'link_superseded');
    }

    public static function revoked(): self
    {
        return new self('Este link foi cancelado pelo clube.', 410, 'link_revoked');
    }

    public static function used(): self
    {
        return new self('Este link já foi usado: a sua resposta ao documento já foi registrada.', 409, 'link_used');
    }

    public static function locked(): self
    {
        return new self(
            'O CPF não conferiu nas tentativas permitidas e este link foi bloqueado. Peça ao clube que envie um novo link.',
            423,
            'link_locked',
        );
    }

    public static function unavailable(string $motivo): self
    {
        return new self($motivo, 409, 'document_unavailable');
    }
}
