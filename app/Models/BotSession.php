<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Onde um contato está na conversa com o bot. Uma linha por contato.
 */
class BotSession extends Model
{
    public const STATE_IDLE = 'idle';
    public const STATE_FLOW = 'flow';
    public const STATE_HUMAN = 'human';

    protected $primaryKey = 'contact_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'contact_uuid',
        'state',
        'flow_slug',
        'step_key',
        'data',
        'tentativas',
        'prompt_message_uuid',
        'attendance_uuid',
        'contact_phone',
        'contact_name',
        'human_since',
        'last_interaction_at',
    ];

    protected $casts = [
        'data' => 'array',
        'tentativas' => 'integer',
        'human_since' => 'datetime',
        'last_interaction_at' => 'datetime',
    ];

    protected $attributes = [
        'state' => self::STATE_IDLE,
        'tentativas' => 0,
    ];

    public function inFlow(): bool
    {
        return $this->state === self::STATE_FLOW && $this->flow_slug !== null && $this->step_key !== null;
    }

    public function isHuman(): bool
    {
        return $this->state === self::STATE_HUMAN;
    }

    /** Sai de qualquer fluxo e esquece as respostas. */
    public function reset(): void
    {
        $this->state = self::STATE_IDLE;
        $this->flow_slug = null;
        $this->step_key = null;
        $this->data = [];
        $this->tentativas = 0;
        $this->prompt_message_uuid = null;
        $this->human_since = null;
    }

    public function toHuman(): void
    {
        $this->reset();
        $this->state = self::STATE_HUMAN;
        $this->human_since = now();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return ($this->data ?? [])[$key] ?? $default;
    }

    public function put(string $key, mixed $value): void
    {
        $data = $this->data ?? [];
        $data[$key] = $value;
        $this->data = $data;
    }
}
