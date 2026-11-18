<?php

namespace App\Http\Middleware;

use App\Services\ApiTokens\ApiTokenGuard;
use App\Services\ApiTokens\ApiTokenManager;
use App\Support\ApiTokens\ApiScopes;
use App\Support\IpRange;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Quem chama a API da assinatura remota: o SERVIDOR do site externo (Next.js,
 * na DMZ) — nunca o navegador de quem assina.
 *
 * Duas travas:
 *
 *  - o token, no header `Authorization: Bearer`:
 *      - desde 18/11/2026, o token gerenciado (`lara_…`, Administração ›
 *        Integrações) com o escopo `assinatura-remota` — validado e registrado
 *        pelo ApiTokenGuard, como o `api_token` (último uso, revogado, vencido);
 *      - o token ANTIGO desta API (`SIGNATURE_REMOTE_API_TOKEN`), enquanto
 *        `signature.remote.legacy_token_enabled`, registrado como "legado".
 *    Nunca o `API_TOKEN` das reservas nem um token só com o escopo `site`: um
 *    vazamento lá não pode virar abertura de assinatura;
 *  - a origem, quando configurada (`SIGNATURE_REMOTE_ALLOWED_IPS`): o IP do
 *    servidor do site.
 *
 * A recusa mantém o formato que o site já trata
 * (`{error, reason: unauthorized}`, 401/403).
 *
 * Passando, resolve o contexto de QUEM ASSINA a partir dos headers que o site
 * repassa — `X-Signer-Ip` e `X-Signer-User-Agent`. Sem isso, a trilha e o
 * manifesto registrariam o IP do servidor do site como se fosse o da pessoa.
 * O IP só é aceito se for um IP válido; inválido ou ausente fica nulo (o
 * manifesto diz "não registrado"), e não o do site.
 */
class EnsureSignatureRemoteClient
{
    /** Onde o contexto de quem assina fica para o controller. */
    public const ATTRIBUTE = 'signature_remote_context';

    public function __construct(private ApiTokenGuard $guard)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $status = $this->authenticate($request);

        if ($status !== null) {
            return response()->json(['error' => 'Não autorizado.', 'reason' => 'unauthorized'], $status);
        }

        $faixas = (array) config('signature.remote.allowed_ips', []);

        if ($faixas !== [] && !IpRange::inAny($request->ip(), $faixas)) {
            return response()->json(['error' => 'Origem não autorizada.', 'reason' => 'unauthorized'], 403);
        }

        $ip = trim((string) $request->header('X-Signer-Ip'));
        $userAgent = trim((string) $request->header('X-Signer-User-Agent'));

        $request->attributes->set(self::ATTRIBUTE, [
            'ip' => filter_var($ip, FILTER_VALIDATE_IP) ? $ip : null,
            'user_agent' => $userAgent === '' ? null : mb_substr($userAgent, 0, 255),
        ]);

        return $next($request);
    }

    /** Null = passou; senão o status HTTP da recusa. Toda chamada fica registrada. */
    private function authenticate(Request $request): ?int
    {
        $scopes = [ApiScopes::ASSINATURA_REMOTA];
        $plain = (string) $request->bearerToken();

        // Token gerenciado: o guard decide (escopo, revogado, vencido) e registra.
        if (str_starts_with($plain, ApiTokenManager::PREFIX)) {
            $check = $this->guard->check($request, $scopes);
            $this->guard->record($request, $scopes, $check['kind'], $check['token']);

            if (! $check['ok']) {
                return $check['status'];
            }

            $request->attributes->set(ApiTokenGuard::ATTRIBUTE, $check['token']);

            return null;
        }

        // Token antigo desta API. Não passa pelo guard.check: lá o legado é o
        // API_TOKEN das reservas, que aqui nunca vale.
        $legacy = (string) config('signature.remote.api_token', '');
        $legacyOn = (bool) config('signature.remote.legacy_token_enabled', true);

        // Sem token configurado nada passa: senão um "Bearer " puro seria aceito.
        if ($plain !== '' && $legacyOn && $legacy !== '' && hash_equals($legacy, $plain)) {
            $this->guard->record($request, $scopes, ApiTokenGuard::KIND_LEGACY, null);

            return null;
        }

        $this->guard->record($request, $scopes, $plain === '' ? ApiTokenGuard::KIND_MISSING : ApiTokenGuard::KIND_REJECTED, null);

        return 401;
    }
}
