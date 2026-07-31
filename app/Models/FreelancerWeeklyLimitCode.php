<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

/**
 * Código de 6 dígitos enviado por e-mail aos coordenadores do Comercial para
 * liberar um serviço acima do limite de 7 dias sem que nenhum deles precise
 * estar presente. Vale uma vez só, para um contrato só, e por pouco tempo.
 *
 * O código é do CONTRATO, não de uma pessoa: o mesmo número vai para todos os
 * coordenadores e quem estiver disponível dita. Por isso `coordinator_id` é
 * nulo — `sent_to` guarda a lista de quem recebeu.
 */
class FreelancerWeeklyLimitCode extends Model
{
    protected $table = 'freelancer_weekly_limit_codes';

    protected $fillable = [
        'coordinator_id',
        'freelancer_id',
        'start_date',
        'code_hash',
        'sent_to',
        'requested_by',
        'expires_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function coordinator()
    {
        return $this->belongsTo(User::class, 'coordinator_id');
    }

    public function freelancer()
    {
        return $this->belongsTo(Freelancer::class);
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /** Ainda vale: não foi usado, não venceu e não estourou as tentativas. */
    public function isPending(int $maxAttempts): bool
    {
        return !$this->isUsed() && !$this->isExpired() && $this->attempts < $maxAttempts;
    }

    public function matches(?string $code): bool
    {
        return filled($code) && Hash::check(trim($code), $this->code_hash);
    }

    /**
     * Códigos daquele contrato, do mais novo para o mais antigo. Um pedido novo
     * invalida os anteriores, então na prática só o primeiro da lista interessa.
     */
    public function scopeFor($query, int $freelancerId, $startDate)
    {
        return $query->where('freelancer_id', $freelancerId)
            ->whereDate('start_date', $startDate)
            ->orderByDesc('id');
    }
}
