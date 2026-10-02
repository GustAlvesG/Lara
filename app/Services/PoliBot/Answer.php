<?php

namespace App\Services\PoliBot;

/**
 * Resultado de conferir uma resposta. `reason` é o tipo esperado que não
 * bateu (plate, date, option, media…), e escolhe a mensagem de correção
 * padrão quando o passo não tem a sua.
 */
class Answer
{
    private function __construct(
        public readonly bool $valid,
        public readonly mixed $value = null,
        public readonly ?array $option = null,
        public readonly ?string $reason = null,
    ) {}

    public static function ok(mixed $value, ?array $option = null): self
    {
        return new self(true, $value, $option);
    }

    public static function invalid(string $reason): self
    {
        return new self(false, reason: $reason);
    }
}
