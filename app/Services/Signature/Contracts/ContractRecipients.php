<?php

namespace App\Services\Signature\Contracts;

use App\Models\SignatureContract;
use App\Models\User;

/**
 * Para quem vão os avisos dos contratos.
 *
 * Isolado porque consulta `users` (conexão `mysql`): os testes trocam esta
 * classe no container e nada vai ao banco de verdade.
 */
class ContractRecipients
{
    /**
     * "Contrato para validar": só o COORDENADOR da Gerência (decisão do
     * usuário, 27/10/2026), não o setor todo.
     *
     * @return array<int, string>
     */
    public function reviewers(): array
    {
        return User::query()
            ->whereHas('sectors', fn($q) => $q
                ->whereRaw('LOWER(sectors.name) = ?', [mb_strtolower(User::MANAGEMENT_SECTOR)])
                ->where('user_sector.role', 'coordinator'))
            ->pluck('email')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** Quem redigiu: recebe a devolução e a aprovação. */
    public function author(SignatureContract $contract): ?string
    {
        return $contract->created_by ? User::find($contract->created_by)?->email : null;
    }
}
