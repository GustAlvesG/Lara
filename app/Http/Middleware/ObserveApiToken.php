<?php

namespace App\Http\Middleware;

use App\Services\ApiTokens\ApiTokenGuard;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `api_token_portaria:<escopo>` — para as rotas que nunca pediram token
 * (`/api/company-access/*`, Monitor de Acesso).
 *
 * Com `api_tokens.portaria_enforced` = false (o padrão), NADA é barrado: a
 * chamada com token válido fica registrada no token, e a chamada sem token
 * (ou com token recusado) passa e fica registrada como "sem token" /
 * "recusado" — a tela Integrações mostra quem ainda chama assim. Ligado,
 * vira exatamente o `api_token`.
 *
 * A config é lida a cada requisição (não no routes/api.php), então ligar
 * não depende de refazer o cache de rotas, só o de config.
 */
class ObserveApiToken
{
    public function __construct(private ApiTokenGuard $guard, private APIToken $strict)
    {
    }

    public function handle(Request $request, Closure $next, string ...$scopes): Response
    {
        if (config('api_tokens.portaria_enforced', false)) {
            return $this->strict->handle($request, $next, ...$scopes);
        }

        if ($request->getMethod() === 'OPTIONS') {
            return $next($request);
        }

        $check = $this->guard->check($request, $scopes);
        $this->guard->record($request, $scopes, $check['kind'], $check['token']);

        if ($check['ok'] && $check['token'] !== null) {
            $request->attributes->set(ApiTokenGuard::ATTRIBUTE, $check['token']);
        }

        return $next($request);
    }
}
