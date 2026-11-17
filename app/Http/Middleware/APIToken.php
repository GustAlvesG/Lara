<?php

namespace App\Http\Middleware;

use App\Services\ApiTokens\ApiTokenGuard;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `api_token:<escopo>[,<escopo>…]` — chamada de sistema externo.
 *
 * Aceita o token da integração (Administração › Integrações) que tenha um
 * dos escopos da rota, ou o `API_TOKEN` antigo do .env enquanto
 * `api_tokens.legacy_enabled` (ver ApiTokenGuard). Toda rota declara o
 * escopo; sem escopo, nenhum token gerenciado passa.
 *
 * O header recebido nunca volta no corpo da resposta (vazava o token para
 * log de proxy).
 */
class APIToken
{
    public function __construct(private ApiTokenGuard $guard)
    {
    }

    public function handle(Request $request, Closure $next, string ...$scopes): Response
    {
        if ($request->getMethod() === 'OPTIONS') {
            return $next($request);
        }

        $check = $this->guard->check($request, $scopes);
        $this->guard->record($request, $scopes, $check['kind'], $check['token']);

        if (! $check['ok']) {
            return response()->json(['message' => $check['message']], $check['status']);
        }

        if ($check['token'] !== null) {
            $request->attributes->set(ApiTokenGuard::ATTRIBUTE, $check['token']);
        }

        return $next($request);
    }
}
