<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Uma linha do chat com a Lara — a pergunta do funcionário e a resposta da IA
 * são duas linhas irmãs, unidas pelo `conversation_uuid`.
 *
 * Linhas nunca são editadas (é histórico), então não há `updated_at`.
 */
class LaraMessage extends Model
{
    const UPDATED_AT = null;

    public const ROLE_USER = 'user';
    public const ROLE_ASSISTANT = 'assistant';

    protected $fillable = [
        'user_id',
        'conversation_uuid',
        'role',
        'conteudo',
        'status',
        'latencia_ms',
        'erro',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'latencia_ms' => 'integer',
    ];

    public function scopeDaConversa(Builder $query, int $userId, string $conversationUuid): Builder
    {
        return $query
            ->where('user_id', $userId)
            ->where('conversation_uuid', $conversationUuid)
            ->orderBy('id');
    }
}
