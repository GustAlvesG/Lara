<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Recusa de um acionamento de luz pelo sócio.
 *
 * Carrega um `reason` estável além da mensagem: a tela do app precisa decidir o
 * que mostrar (um contador regressivo, o horário da próxima janela, o fim da
 * reserva de outro sócio) e não pode fazer isso lendo texto em português, que
 * muda quando alguém melhora a redação.
 */
class SelfServiceLightingException extends RuntimeException
{
    /** Fora do sábado/domingo à tarde, ou num dia bloqueado no painel. */
    public const CLOSED = 'window_closed';

    /** Dentro da janela, mas falta menos que o mínimo para o fim dela. */
    public const TOO_LATE = 'window_ending';

    /**
     * O sócio já está em outra quadra.
     *
     * Quadra **já acesa não é recusa**: qualquer sócio presente prolonga a luz
     * de onde está jogando, e acionar a própria quadra de novo é justamente o
     * mecanismo de prolongar. O que não se pode é ter duas quadras acesas.
     */
    public const ALREADY_ON = 'member_limit';

    /** O espaço não está liberado para autoatendimento (ou perdeu o contator). */
    public const NOT_ELIGIBLE = 'place_not_eligible';

    /** Há reserva confirmada na quadra: ela tem dono e a luz já acende sozinha. */
    public const RESERVED = 'place_reserved';

    /**
     * @param array<string, mixed> $context
     */
    private function __construct(
        public readonly string $reason,
        public readonly int $status,
        string $message,
        public readonly array $context = [],
    ) {
        parent::__construct($message);
    }

    /** @param array<string, mixed> $context */
    public static function closed(string $message, array $context = []): self
    {
        return new self(self::CLOSED, 422, $message, $context);
    }

    /** @param array<string, mixed> $context */
    public static function tooLate(string $message, array $context = []): self
    {
        return new self(self::TOO_LATE, 422, $message, $context);
    }

    /** @param array<string, mixed> $context */
    public static function alreadyOn(string $message, array $context = []): self
    {
        return new self(self::ALREADY_ON, 409, $message, $context);
    }

    public static function notEligible(string $message): self
    {
        return new self(self::NOT_ELIGIBLE, 422, $message);
    }

    /** @param array<string, mixed> $context */
    public static function reserved(string $message, array $context = []): self
    {
        return new self(self::RESERVED, 409, $message, $context);
    }


    /** @return array<string, mixed> */
    public function payload(): array
    {
        return ['error' => $this->getMessage(), 'reason' => $this->reason] + $this->context;
    }
}
