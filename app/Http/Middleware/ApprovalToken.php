<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Providers\Services\JwtService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autenticação do aprovador externo (site em DMZ / Postman).
 *
 * Reaproveita o `JwtService` que já assina os tokens do app de sócios, mas com
 * um **escopo próprio** no payload: sem a checagem de `scope`, um token de
 * sócio — assinado com a mesma chave — abriria a aprovação de ordem de compra.
 *
 * O token traz só o id do usuário e o escopo; tudo o que decide permissão
 * (estar no setor Diretoria, ter passo pendente) é relido do banco a cada
 * requisição. Um diretor removido do setor perde o acesso na hora, sem esperar
 * o token expirar.
 */
class ApprovalToken
{
    public const SCOPE = 'purchase-approval';

    public function __construct(private readonly JwtService $jwt)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->getMethod() === 'OPTIONS') {
            return $next($request);
        }

        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['error' => 'Token de aprovação ausente.'], 401);
        }

        try {
            $payload = $this->jwt->validateToken($token);
        } catch (\Throwable $e) {
            // A mensagem não distingue expirado de inválido de propósito: para
            // quem está do lado de fora, as duas coisas pedem o mesmo remédio.
            return response()->json(['error' => 'Token de aprovação inválido ou expirado.'], 401);
        }

        if (($payload['scope'] ?? null) !== self::SCOPE) {
            return response()->json(['error' => 'Este token não vale para aprovação de compras.'], 403);
        }

        $user = User::find($payload['uid'] ?? 0);

        if (!$user || (int) $user->status_id !== 1 || !$user->isDirector()) {
            return response()->json(['error' => 'Usuário sem acesso à aprovação de compras.'], 403);
        }

        // As rotas seguintes usam `$request->user()` igual às do painel.
        $request->setUserResolver(fn() => $user);

        return $next($request);
    }
}
