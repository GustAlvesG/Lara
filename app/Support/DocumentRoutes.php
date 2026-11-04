<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;

/**
 * O nome de rota de um documento de assinatura, no módulo em que a tela está.
 *
 * O documento gerado de um contrato passa pelo MESMO processo de Assinaturas
 * (mesmos controllers e telas), mas dentro do módulo Contratos, na aba
 * Assinaturas (`contract-signatures.*`, desde 04/11/2026). As telas e os
 * redirecionamentos pedem a rota por aqui: dentro da aba de Contratos, os
 * links e os formulários continuam nela; fora dela (Assinaturas, e-mail,
 * comando), saem como `signature-documents.*`.
 *
 * Rota que só existe em Assinaturas (novo documento, enviar PDF pronto) cai
 * sempre em `signature-documents.*`.
 */
final class DocumentRoutes
{
    public const DOCUMENTS = 'signature-documents.';
    public const CONTRACTS = 'contract-signatures.';

    /** A requisição atual está na aba Assinaturas do módulo Contratos? */
    public static function inContracts(): bool
    {
        $atual = Route::currentRouteName();

        return is_string($atual) && str_starts_with($atual, self::CONTRACTS);
    }

    public static function name(string $suffix): string
    {
        if (self::inContracts() && Route::has(self::CONTRACTS . $suffix)) {
            return self::CONTRACTS . $suffix;
        }

        return self::DOCUMENTS . $suffix;
    }

    public static function route(string $suffix, mixed $parameters = [], bool $absolute = true): string
    {
        return route(self::name($suffix), $parameters, $absolute);
    }
}
