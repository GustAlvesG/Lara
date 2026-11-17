<?php

namespace App\Services\ApiTokens;

use App\Models\ApiTokens\ApiIntegrationToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Decide se a chamada externa passa, e registra o uso.
 *
 * Ordem:
 *   1. token gerenciado (`lara_…`): achado pelo SHA-256; precisa estar ativo,
 *      dentro da validade, com a integração ativa e com um dos escopos da
 *      rota. Grava o último uso no token.
 *   2. `API_TOKEN` antigo do .env, enquanto `api_tokens.legacy_enabled`:
 *      passa em qualquer rota (como sempre passou), registrado como "legado"
 *      com IP e rota — é o que a tela usa para saber quem ainda depende dele.
 *   3. o resto é recusado (e registrado como "recusado").
 *
 * O registro nunca derruba a chamada: falha de banco ali vira só um aviso
 * no log.
 */
class ApiTokenGuard
{
    public const KIND_TOKEN = 'token';
    public const KIND_LEGACY = 'legado';
    public const KIND_MISSING = 'sem-token';
    public const KIND_REJECTED = 'recusado';

    public const ATTRIBUTE = 'api_integration_token';

    /**
     * @param  list<string>  $scopes  escopos aceitos pela rota (qualquer um basta)
     * @return array{ok: bool, status: int, message: string, kind: string, token: ?ApiIntegrationToken}
     */
    public function check(Request $request, array $scopes): array
    {
        $plain = (string) $request->bearerToken();

        if ($plain === '') {
            return $this->result(false, 401, 'Invalid API Token', self::KIND_MISSING);
        }

        if (str_starts_with($plain, ApiTokenManager::PREFIX)) {
            return $this->checkManaged($plain, $scopes);
        }

        $legacy = (string) config('services.api.token');
        if (config('api_tokens.legacy_enabled', true) && $legacy !== '' && hash_equals($legacy, $plain)) {
            return $this->result(true, 200, '', self::KIND_LEGACY);
        }

        return $this->result(false, 401, 'Invalid API Token', self::KIND_REJECTED);
    }

    /** Grava o último uso no token e soma a chamada no dia. */
    public function record(Request $request, array $scopes, string $kind, ?ApiIntegrationToken $token): void
    {
        try {
            $ip = (string) $request->ip();
            $route = Str::limit((string) ($request->route()?->getName() ?: $request->path()), 185, '');
            $now = now();

            // Último uso só da chamada aceita: a recusada (revogado, escopo
            // errado) fica na contagem do dia, com o id do token.
            if ($token !== null && $kind === self::KIND_TOKEN) {
                $stale = $token->last_used_at === null
                    || $token->last_used_at->diffInSeconds($now, true) >= (int) config('api_tokens.touch_seconds', 60)
                    || $token->last_used_ip !== $ip
                    || $token->last_used_route !== $route;

                if ($stale) {
                    // Pelo query builder: não mexe no updated_at do token.
                    DB::table('api_integration_tokens')->where('id', $token->id)->update([
                        'last_used_at' => $now,
                        'last_used_ip' => $ip,
                        'last_used_route' => $route,
                    ]);
                }
            }

            DB::table('api_token_usage_daily')->upsert(
                [[
                    'day' => $now->toDateString(),
                    'api_integration_token_id' => $token?->id ?? 0,
                    'kind' => $kind,
                    'scope' => Str::limit(implode(',', $scopes) ?: '-', 40, ''),
                    'ip' => $ip,
                    'hits' => 1,
                    'last_route' => $route,
                    'last_seen_at' => $now,
                ]],
                ['day', 'api_integration_token_id', 'kind', 'scope', 'ip'],
                ['hits' => DB::raw('hits + 1'), 'last_route' => $route, 'last_seen_at' => $now],
            );
        } catch (Throwable $e) {
            Log::warning('Falha ao registrar o uso de token da API', ['erro' => $e->getMessage()]);
        }
    }

    private function checkManaged(string $plain, array $scopes): array
    {
        try {
            $token = ApiIntegrationToken::with('integration')
                ->where('token_hash', ApiTokenManager::hash($plain))
                ->first();
        } catch (Throwable $e) {
            Log::error('Falha ao consultar token da API', ['erro' => $e->getMessage()]);

            return $this->result(false, 401, 'Invalid API Token', self::KIND_REJECTED);
        }

        if ($token === null) {
            return $this->result(false, 401, 'Invalid API Token', self::KIND_REJECTED);
        }
        if (! $token->isUsable()) {
            return $this->result(false, 401, 'Token revogado, vencido ou de integração desativada', self::KIND_REJECTED, $token);
        }
        if ($scopes === [] || ! $token->hasAnyScope($scopes)) {
            return $this->result(false, 403, 'Este token não tem acesso a esta API', self::KIND_REJECTED, $token);
        }

        return $this->result(true, 200, '', self::KIND_TOKEN, $token);
    }

    private function result(bool $ok, int $status, string $message, string $kind, ?ApiIntegrationToken $token = null): array
    {
        return compact('ok', 'status', 'message', 'kind', 'token');
    }
}
