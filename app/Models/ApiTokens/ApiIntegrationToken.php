<?php

namespace App\Models\ApiTokens;

use App\Models\User;
use App\Support\ApiTokens\ApiScopes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Token de uma instalação de uma integração. Só o SHA-256 fica no banco
 * (`token_hash`); `token_hint` guarda os 4 últimos caracteres para a tela
 * identificar qual token está instalado onde.
 *
 * O último uso (`last_used_at`, `last_used_ip`, `last_used_route`) é gravado
 * pelo ApiTokenGuard a cada chamada aceita, no máximo uma vez por
 * `api_tokens.touch_seconds`.
 */
class ApiIntegrationToken extends Model
{
    public const STATUS_ACTIVE = 'ativo';
    public const STATUS_EXPIRING = 'vencendo';
    public const STATUS_EXPIRED = 'vencido';
    public const STATUS_REVOKED = 'revogado';

    protected $fillable = [
        'api_integration_id', 'name', 'token_hash', 'token_hint', 'scopes', 'expires_at',
        'last_used_at', 'last_used_ip', 'last_used_route',
        'revoked_at', 'revoked_by', 'revoke_reason', 'replaced_by_id', 'created_by',
    ];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(ApiIntegration::class, 'api_integration_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function replacedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaced_by_id');
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /** Vale agora: não revogado, dentro da validade e com a integração ativa. */
    public function isUsable(): bool
    {
        return ! $this->isRevoked() && ! $this->isExpired() && (bool) $this->integration?->active;
    }

    public function hasAnyScope(array $scopes): bool
    {
        return array_intersect($scopes, (array) $this->scopes) !== [];
    }

    public function status(): string
    {
        if ($this->isRevoked()) {
            return self::STATUS_REVOKED;
        }
        if ($this->isExpired()) {
            return self::STATUS_EXPIRED;
        }
        if ($this->expires_at !== null && $this->expires_at->lte(now()->addDays((int) config('api_tokens.expiring_days', 30)))) {
            return self::STATUS_EXPIRING;
        }

        return self::STATUS_ACTIVE;
    }

    /** @return list<string> rótulos dos escopos, na ordem do catálogo */
    public function scopeLabels(): array
    {
        return array_values(array_map(
            fn (string $s) => ApiScopes::label($s),
            array_values(array_intersect(ApiScopes::all(), (array) $this->scopes)),
        ));
    }
}
