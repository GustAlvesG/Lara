<?php

namespace App\Services\MemberValidation;

use App\Models\UberAccessRequest;

class MemberValidationResult
{
    private function __construct(
        public readonly string $status,
        public readonly ?string $matchedName = null,
        public readonly ?string $type = null,
        public readonly ?int $employeeId = null,
        public readonly ?string $detail = null,
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

    /** Nome informado bate com um funcionário apto da matrícula/CPF. */
    public static function funcionario(string $matchedName, ?int $employeeId = null): self
    {
        return new self(
            UberAccessRequest::MEMBER_VALIDATION_VALIDADO,
            $matchedName,
            UberAccessRequest::MEMBER_TYPE_FUNCIONARIO,
            $employeeId
        );
    }

    /**
     * O nome bate com o funcionário, mas ele está desligado, de férias ou
     * afastado. Distinto de "não encontrado": a pessoa é quem diz ser, só não
     * pode usar o benefício agora — e a portaria precisa saber o porquê.
     */
    public static function funcionarioBloqueado(string $matchedName, ?int $employeeId, string $reason): self
    {
        return new self(
            UberAccessRequest::MEMBER_VALIDATION_FUNCIONARIO_BLOQUEADO,
            $matchedName,
            UberAccessRequest::MEMBER_TYPE_FUNCIONARIO,
            $employeeId,
            $reason
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
