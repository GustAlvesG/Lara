<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Onde um contato está na conversa com o bot. Uma linha por contato.
 *
 * `lara_owned` separa as conversas atribuídas ao usuário O Lara (a Lara fala
 * de verdade) das que continuam no bot da Poli e passam pela Lara
 * só em sombra, para comparação.
 */
class BotSession extends Model
{
    public const STATE_IDLE = 'idle';
    public const STATE_FLOW = 'flow';
    public const STATE_HUMAN = 'human';

    /** Fluxo concluído; o atendimento fecha em `ending_at` se ninguém escrever. */
    public const STATE_ENDING = 'ending';

    protected $primaryKey = 'contact_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'contact_uuid',
        'state',
        'lara_owned',
        'flow_slug',
        'step_key',
        'data',
        'tentativas',
        'prompt_message_uuid',
        'attendance_uuid',
        'closed_attendance_uuid',
        'closed_at',
        'contact_phone',
        'contact_name',
        'human_since',
        'ending_at',
        'last_interaction_at',
    ];

    protected $casts = [
        'data' => 'array',
        'lara_owned' => 'boolean',
        'tentativas' => 'integer',
        'human_since' => 'datetime',
        'ending_at' => 'datetime',
        'closed_at' => 'datetime',
        'last_interaction_at' => 'datetime',
    ];

    protected $attributes = [
        'state' => self::STATE_IDLE,
        'lara_owned' => false,
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

    public function isEnding(): bool
    {
        return $this->state === self::STATE_ENDING;
    }

    /**
     * Sai de qualquer fluxo e esquece as respostas — dentro da mesma conversa
     * (menu, prazo vencido): quem é o dono não muda. O registro do último
     * atendimento que a Lara encerrou fica: é dele que o resgate precisa.
     */
    public function reset(): void
    {
        $this->state = self::STATE_IDLE;
        $this->flow_slug = null;
        $this->step_key = null;
        $this->data = [];
        $this->tentativas = 0;
        $this->prompt_message_uuid = null;
        $this->human_since = null;
        $this->ending_at = null;
    }

    /** O atendimento acabou: a próxima mensagem é conversa nova, de quem for. */
    public function endConversation(): void
    {
        $this->reset();
        $this->lara_owned = false;
    }

    public function toHuman(): void
    {
        $this->reset();
        $this->state = self::STATE_HUMAN;
        $this->human_since = now();
    }

    /**
     * Conversa do O Lara sem pergunta em aberto: fecha no prazo se o contato
     * não escrever.
     */
    public function toEnding(int $minutes): void
    {
        $this->reset();
        $this->state = self::STATE_ENDING;
        $this->lara_owned = true;
        $this->ending_at = now()->addMinutes($minutes);
    }

    /** A própria Lara encerrou este atendimento. */
    public function markClosedByLara(): void
    {
        if (filled($this->attendance_uuid)) {
            $this->closed_attendance_uuid = $this->attendance_uuid;
            $this->closed_at = now();
        }

        $this->endConversation();
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
