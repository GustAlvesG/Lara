<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * O processo de aprovação de uma ordem de compra.
 *
 * Três níveis em sequência — Contabilidade, Gerência, Diretoria — e o Questor
 * só é tocado quando o terceiro fecha. Enquanto isso, tudo o que aconteceu mora
 * aqui e nos passos.
 *
 * **Cada nível é uma decisão própria.** Mesmo que a mesma pessoa ocupe os três
 * cargos, aprovar a Contabilidade não aprova a Gerência: são três cliques, em
 * três momentos, com três registros. É o ponto do fluxo existir.
 */
class PurchaseOrderApproval extends Model
{
    public const STATUS_OPEN = 'open';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public const LEVEL_ACCOUNTING = 1;
    public const LEVEL_MANAGEMENT = 2;
    public const LEVEL_DIRECTORS = 3;

    public const LEVEL_LABELS = [
        self::LEVEL_ACCOUNTING => 'Contabilidade',
        self::LEVEL_MANAGEMENT => 'Gerência',
        self::LEVEL_DIRECTORS => 'Diretoria',
    ];

    protected $fillable = [
        'cd_ordem_compra',
        'cd_filial',
        'vl_total',
        'nr_itens',
        'cost_centers',
        'sem_centro_custo',
        'status',
        'current_level',
        'started_by',
        'closed_at',
        'questor_decision_id',
    ];

    protected $casts = [
        'cd_ordem_compra' => 'integer',
        'cd_filial' => 'integer',
        'vl_total' => 'decimal:2',
        'nr_itens' => 'integer',
        'cost_centers' => 'array',
        'sem_centro_custo' => 'boolean',
        'current_level' => 'integer',
        'started_by' => 'integer',
        'closed_at' => 'datetime',
        'questor_decision_id' => 'integer',
    ];

    public function steps()
    {
        return $this->hasMany(PurchaseOrderApprovalStep::class, 'approval_id');
    }

    public function startedBy()
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function scopeOpen($query)
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    /** O processo aberto de uma ordem, se houver. */
    public static function openFor(int $cdOrdemCompra): ?self
    {
        return self::open()
            ->where('cd_ordem_compra', $cdOrdemCompra)
            ->with('steps')
            ->latest('id')
            ->first();
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function currentLevelLabel(): string
    {
        return self::LEVEL_LABELS[$this->current_level] ?? (string) $this->current_level;
    }

    /**
     * Os passos do nível em curso.
     *
     * @return Collection<int, PurchaseOrderApprovalStep>
     */
    public function currentSteps(): Collection
    {
        return $this->steps->where('level', $this->current_level)->values();
    }

    /**
     * Os passos do nível em curso que ainda esperam decisão.
     *
     * @return Collection<int, PurchaseOrderApprovalStep>
     */
    public function pendingSteps(): Collection
    {
        return $this->currentSteps()->where('decision', PurchaseOrderApprovalStep::PENDING)->values();
    }

    /**
     * O passo que este usuário pode decidir agora, se houver.
     *
     * Devolve `null` quando não é a vez dele — inclusive quando ele já decidiu
     * este nível. Um diretor que já aprovou não aprova de novo.
     */
    public function stepFor(User $user): ?PurchaseOrderApprovalStep
    {
        return $this->pendingSteps()->first(function (PurchaseOrderApprovalStep $step) use ($user) {
            if ($step->user_id !== null) {
                return $step->user_id === $user->id;
            }

            return match ($step->role) {
                PurchaseOrderApprovalStep::ROLE_ACCOUNTING => $user->isAccountingCoordinator(),
                PurchaseOrderApprovalStep::ROLE_MANAGEMENT => $user->isManagementCoordinator(),
                default => false,
            };
        });
    }

    /**
     * Resumo legível de quem aprovou o quê — o texto que vai para
     * `DS_OBS` no Questor e para a tela.
     */
    public function approversSummary(): string
    {
        return $this->steps
            ->where('decision', PurchaseOrderApprovalStep::APPROVED)
            ->sortBy(['level', 'decided_at'])
            ->groupBy('level')
            ->map(function (Collection $doNivel, $nivel) {
                $nomes = $doNivel
                    ->map(fn(PurchaseOrderApprovalStep $s) => trim(
                        ($s->decided_by_name ?: 'usuário ' . $s->decided_by)
                        . ' (' . ($s->decided_at?->format('d/m H:i') ?? '') . ')'
                    ))
                    ->implode(', ');

                return (self::LEVEL_LABELS[(int) $nivel] ?? $nivel) . ': ' . $nomes;
            })
            ->implode('; ');
    }
}
