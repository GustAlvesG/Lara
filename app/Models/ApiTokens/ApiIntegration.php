<?php

namespace App\Models\ApiTokens;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Um sistema externo que chama O Lara (Administração › Integrações). Os
 * tokens são por instalação, em ApiIntegrationToken. Desativar a integração
 * corta todos os tokens dela de uma vez, sem revogá-los.
 */
class ApiIntegration extends Model
{
    /** sistema => rótulo. O mesmo nome dos destinos dos prompts (docs/prompts). */
    public const SYSTEMS = [
        'nextjs' => 'Site (Next.js)',
        'ia' => 'HAL (IA)',
        'portaria' => 'Portaria (Lara Externos / Monitor de Acesso)',
        'home-assistant' => 'Home Assistant',
        'telegram' => 'Bot do Telegram',
        'poli' => 'Poli (WhatsApp)',
        'outro' => 'Outro',
    ];

    protected $fillable = ['name', 'system', 'notes', 'active', 'created_by'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(ApiIntegrationToken::class)->latest('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function systemLabel(): string
    {
        return self::SYSTEMS[$this->system] ?? $this->system;
    }

    /** O uso mais recente entre os tokens (carregados), para o cartão da lista. */
    public function lastUsedAt(): ?\Illuminate\Support\Carbon
    {
        return $this->tokens->pluck('last_used_at')->filter()->sortDesc()->first();
    }
}
