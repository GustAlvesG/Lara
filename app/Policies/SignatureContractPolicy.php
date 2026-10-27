<?php

namespace App\Policies;

use App\Authorization\Permissions as P;
use App\Models\SignatureContract;
use App\Models\User;

/**
 * Quem pode o quê com um contrato (Assinaturas → Contratos).
 *
 * Duas regras, ambas decisão do usuário (27/10/2026):
 *
 *  - cada setor só vê os próprios contratos — redigir pede a permissão
 *    `assinatura.contratos` E ser do setor do contrato;
 *  - só a Gerência valida (e por isso enxerga os de todos os setores). É
 *    cargo, não permissão: estar no setor Gerência (`isManagementMember`).
 *    A Gerência pode validar o que ela mesma redigiu.
 *
 * As regras de estado (o que cada situação permite) são do ContractService;
 * aqui fica só QUEM.
 */
class SignatureContractPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(P::ASSINATURA_CONTRATOS) || $user->isManagementMember();
    }

    public function view(User $user, SignatureContract $contract): bool
    {
        return $user->isManagementMember() || $this->isSectorWriter($user, $contract);
    }

    public function create(User $user): bool
    {
        return $user->can(P::ASSINATURA_CONTRATOS);
    }

    /**
     * Mexer no texto: o setor enquanto é rascunho ou devolvido; a Gerência
     * também enquanto valida (ajusta direto, em vez de devolver).
     */
    public function update(User $user, SignatureContract $contract): bool
    {
        if ($contract->status === SignatureContract::STATUS_IN_REVIEW) {
            return $user->isManagementMember();
        }

        return $contract->isEditableBySector()
            && ($this->isSectorWriter($user, $contract) || $user->isManagementMember());
    }

    public function submit(User $user, SignatureContract $contract): bool
    {
        return $contract->isEditableBySector()
            && ($this->isSectorWriter($user, $contract) || $user->isManagementMember());
    }

    /** Aprovar ou devolver. */
    public function decide(User $user, SignatureContract $contract): bool
    {
        return $contract->status === SignatureContract::STATUS_IN_REVIEW && $user->isManagementMember();
    }

    /**
     * Pedir a revisão automática pelo Claude: a Gerência, enquanto o texto
     * ainda pode mudar (rascunho, devolvido ou em validação).
     */
    public function aiReview(User $user, SignatureContract $contract): bool
    {
        return $user->isManagementMember()
            && in_array($contract->status, [
                SignatureContract::STATUS_DRAFT,
                SignatureContract::STATUS_RETURNED,
                SignatureContract::STATUS_IN_REVIEW,
            ], true);
    }

    public function manage(User $user, SignatureContract $contract): bool
    {
        return $this->view($user, $contract);
    }

    /** Criar aditivo: de quem redige pelo setor. */
    public function addendum(User $user, SignatureContract $contract): bool
    {
        return $contract->status === SignatureContract::STATUS_APPROVED
            && ($this->isSectorWriter($user, $contract) || ($user->isManagementMember() && $user->can(P::ASSINATURA_CONTRATOS)));
    }

    private function isSectorWriter(User $user, SignatureContract $contract): bool
    {
        return $user->can(P::ASSINATURA_CONTRATOS)
            && in_array((int) $contract->sector_id, $user->sectorIds(), true);
    }
}
