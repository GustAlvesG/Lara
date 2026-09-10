<?php

namespace App\Services\MemberValidation;

use App\Models\UberAccessRequest;

class MemberValidationResult
{
    private function __construct(
        public readonly string $status,
        public readonly ?string $matchedName = null,
        public readonly ?string $type = null,
    ) {}

    /** Nome informado bate com alguém do título no MultiClubes. */
    public static function socio(string $matchedName): self
    {
        return new self(
            UberAccessRequest::MEMBER_VALIDATION_VALIDADO,
            $matchedName,
            UberAccessRequest::MEMBER_TYPE_SOCIO
        );
    }

    /** Nome informado bate com o funcionário da matrícula/CPF. */
    public static function funcionario(string $matchedName): self
    {
        return new self(
            UberAccessRequest::MEMBER_VALIDATION_VALIDADO,
            $matchedName,
            UberAccessRequest::MEMBER_TYPE_FUNCIONARIO
        );
    }

    /** Ninguém — sócio ou funcionário — com esse identificador e esse nome. */
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
