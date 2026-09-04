<?php

namespace App\Services\MultiClubes;

use App\Models\UberAccessRequest;

class MemberValidationResult
{
    private function __construct(
        public readonly string $status,
        public readonly ?string $matchedName = null,
    ) {}

    /** Nome informado bate com alguém do título. */
    public static function validado(string $matchedName): self
    {
        return new self(UberAccessRequest::MEMBER_VALIDATION_VALIDADO, $matchedName);
    }

    /** Título inexistente/inativo, ou nenhuma pessoa dele bate com o nome. */
    public static function naoEncontrado(): self
    {
        return new self(UberAccessRequest::MEMBER_VALIDATION_NAO_ENCONTRADO);
    }

    /**
     * Não deu para consultar o MultiClubes. Distinto de "não encontrado" de
     * propósito: aqui o pedido não é suspeito, apenas não foi conferido.
     */
    public static function indisponivel(): self
    {
        return new self(UberAccessRequest::MEMBER_VALIDATION_INDISPONIVEL);
    }
}
