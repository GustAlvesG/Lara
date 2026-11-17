<?php

namespace App\Services\ApiTokens;

use App\Models\ApiTokens\ApiIntegration;
use App\Models\ApiTokens\ApiIntegrationToken;
use App\Models\User;
use App\Support\ApiTokens\ApiScopes;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Gera, rotaciona e revoga os tokens das integrações (Administração ›
 * Integrações). O texto do token só existe no retorno de generate()/rotate():
 * o banco guarda o SHA-256, e a tela mostra o texto uma única vez.
 *
 * Formato: `lara_` + 48 caracteres aleatórios. O prefixo separa o token
 * gerenciado do `API_TOKEN` antigo do .env sem consultar o banco (ver
 * ApiTokenGuard).
 */
class ApiTokenManager
{
    public const PREFIX = 'lara_';

    public static function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }

    /**
     * @param  list<string>  $scopes
     * @return array{token: ApiIntegrationToken, plain: string}
     */
    public function generate(
        ApiIntegration $integration,
        string $name,
        array $scopes,
        ?CarbonInterface $expiresAt,
        ?User $by = null,
    ): array {
        $scopes = $this->normalizeScopes($scopes);
        $name = trim($name);

        if ($name === '') {
            throw new InvalidArgumentException('Informe onde o token vai ser instalado.');
        }
        if ($expiresAt !== null && $expiresAt->isPast()) {
            throw new InvalidArgumentException('A validade precisa ser uma data futura.');
        }

        $plain = self::PREFIX . Str::random(48);

        $token = $integration->tokens()->create([
            'name' => $name,
            'token_hash' => self::hash($plain),
            'token_hint' => '…' . substr($plain, -4),
            'scopes' => $scopes,
            'expires_at' => $expiresAt,
            'created_by' => $by?->id,
        ]);

        Log::info('Token de integração gerado', [
            'integracao' => $integration->name, 'instalacao' => $name, 'token_id' => $token->id,
            'escopos' => $scopes, 'validade' => $expiresAt?->toDateTimeString(), 'por' => $by?->id,
        ]);

        return ['token' => $token, 'plain' => $plain];
    }

    /**
     * Token novo com a mesma instalação e os mesmos escopos. O antigo continua
     * valendo por `$graceDays` (0 = revoga na hora), para o outro sistema
     * trocar sem cair.
     *
     * @return array{token: ApiIntegrationToken, plain: string}
     */
    public function rotate(
        ApiIntegrationToken $old,
        ?CarbonInterface $expiresAt,
        int $graceDays,
        ?User $by = null,
    ): array {
        if ($old->isRevoked()) {
            throw new InvalidArgumentException('Este token já foi revogado: gere um novo.');
        }

        return DB::transaction(function () use ($old, $expiresAt, $graceDays, $by) {
            $new = $this->generate($old->integration, $old->name, (array) $old->scopes, $expiresAt, $by);

            $old->replaced_by_id = $new['token']->id;

            if ($graceDays <= 0) {
                $old->revoked_at = now();
                $old->revoked_by = $by?->id;
                $old->revoke_reason = 'Rotacionado';
            } else {
                $limit = now()->addDays($graceDays);
                if ($old->expires_at === null || $old->expires_at->gt($limit)) {
                    $old->expires_at = $limit;
                }
            }
            $old->save();

            return $new;
        });
    }

    public function revoke(ApiIntegrationToken $token, ?User $by = null, ?string $reason = null): void
    {
        if ($token->isRevoked()) {
            return;
        }

        $token->forceFill([
            'revoked_at' => now(),
            'revoked_by' => $by?->id,
            'revoke_reason' => $reason !== null ? Str::limit(trim($reason), 250, '') : null,
        ])->save();

        Log::info('Token de integração revogado', ['token_id' => $token->id, 'por' => $by?->id]);
    }

    /** @return list<string> */
    private function normalizeScopes(array $scopes): array
    {
        $scopes = array_values(array_unique(array_filter($scopes, 'is_string')));

        if ($scopes === []) {
            throw new InvalidArgumentException('Escolha pelo menos um escopo.');
        }
        foreach ($scopes as $scope) {
            if (! ApiScopes::exists($scope)) {
                throw new InvalidArgumentException("Escopo desconhecido: {$scope}.");
            }
        }

        // Na ordem do catálogo, para a tela e a comparação ficarem estáveis.
        return array_values(array_intersect(ApiScopes::all(), $scopes));
    }
}
