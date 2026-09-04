<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Uma decisão dentro do processo — pendente ou tomada.
 *
 * Níveis 1 e 2 têm um passo cada, preso a um **cargo** (`role`): quem decide é
 * quem ocupa o cargo na hora, e trocar o coordenador não deixa processos
 * órfãos. O nível 3 tem um passo por diretor escolhido, preso à **pessoa**.
 *
 * `source` distingue o diretor que veio do cadastro de centro de custo daquele
 * que o gerente escolheu na mão. Como é o próprio gerente quem pode trocar a
 * lista, essa distinção é o que permite auditar a escolha depois.
 */
class PurchaseOrderApprovalStep extends Model
{
    public const PENDING = 'pending';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';
    /** Passo que não chegou a ser decidido porque o processo fechou antes. */
    public const SKIPPED = 'skipped';

    public const ROLE_ACCOUNTING = 'accounting_coordinator';
    public const ROLE_MANAGEMENT = 'management_coordinator';

    public const SOURCE_SUGGESTED = 'suggested';
    public const SOURCE_MANUAL = 'manual';

    protected $fillable = [
        'approval_id',
        'level',
        'role',
        'user_id',
        'source',
        'decision',
        'decided_by',
        'decided_by_name',
        'decided_at',
        'decided_ip',
        'note',
    ];

    protected $casts = [
        'level' => 'integer',
        'user_id' => 'integer',
        'decided_by' => 'integer',
        'decided_at' => 'datetime',
    ];

    public function approval()
    {
        return $this->belongsTo(PurchaseOrderApproval::class, 'approval_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isPending(): bool
    {
        return $this->decision === self::PENDING;
    }

    public function wasApproved(): bool
    {
        return $this->decision === self::APPROVED;
    }

    /** Rótulo de quem responde por este passo, para a tela. */
    public function assigneeLabel(): string
    {
        if ($this->user_id !== null) {
            return $this->user?->name ?? ('usuário ' . $this->user_id);
        }

        return match ($this->role) {
            self::ROLE_ACCOUNTING => 'Coordenador da Contabilidade',
            self::ROLE_MANAGEMENT => 'Coordenador da Gerência',
            default => '—',
        };
    }
}
