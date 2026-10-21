<?php

namespace App\Http\Middleware;

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
 *  - o token PRÓPRIO desta API (`SIGNATURE_REMOTE_API_TOKEN`), no header
 *    `Authorization: Bearer`. Não é o `API_TOKEN` compartilhado das reservas:
 *    um vazamento lá não pode virar abertura de assinatura;
 *  - a origem, quando configurada (`SIGNATURE_REMOTE_ALLOWED_IPS`): o IP do
 *    servidor do site.
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

    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) config('signature.remote.api_token', '');

        // Sem token configurado nada passa: senão um "Bearer " puro seria aceito.
        if ($token === '' || !hash_equals('Bearer ' . $token, (string) $request->header('Authorization'))) {
            return response()->json(['error' => 'Não autorizado.', 'reason' => 'unauthorized'], 401);
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
}
