<?php

namespace App\Policies;

use App\Authorization\Permissions as P;
use App\Models\SignatureContract;
use App\Models\User;
use App\Services\Signature\Contracts\ContractClub;
use App\Services\Signature\Contracts\ContractService;

/**
 * Quem pode o quê com um contrato (módulo Contratos; até 04/11/2026 era
 * Assinaturas → Contratos).
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
    /**
     * A lista: quem redige, a Gerência e o presidente (05/11/2026: ele
     * acompanha e recusa o que aguarda a assinatura dele).
     */
    public function viewAny(User $user): bool
    {
        return $user->can(P::ASSINATURA_CONTRATOS) || $user->isManagementMember() || $this->isPresident($user);
    }

    public function view(User $user, SignatureContract $contract): bool
    {
        return $user->isManagementMember()
            || $this->isSectorWriter($user, $contract)
            || $this->refuseSignature($user, $contract);
    }

    /**
     * Recusar a assinatura pela tela do contrato (05/11/2026): só o
     * presidente, com o documento aguardando a assinatura dele. Recusa
     * devolve o contrato a quem redigiu (ContractService::refuseByPresident).
     */
    public function refuseSignature(User $user, SignatureContract $contract): bool
    {
        return $contract->status === SignatureContract::STATUS_APPROVED
            && $this->isPresident($user)
            && app(ContractService::class)->presidentSignerFor($contract, (int) $user->id) !== null;
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
     * Pedir a revisão automática pelo Claude: quem pode mexer no texto agora.
     * O setor durante a redação (rascunho ou devolvido, decisão do usuário,
     * 30/10/2026); a Gerência também durante a validação.
     */
    public function aiReview(User $user, SignatureContract $contract): bool
    {
        return $this->update($user, $contract);
    }

    /** Anexos, reabrir, cancelar, gerar documento: o setor e a Gerência (o presidente só lê e recusa). */
    public function manage(User $user, SignatureContract $contract): bool
    {
        return $user->isManagementMember() || $this->isSectorWriter($user, $contract);
    }

    /**
     * Cancelar (05/11/2026): o setor e a Gerência, até a assinatura terminar
     * (`SignatureContract::canBeCanceled`).
     */
    public function cancel(User $user, SignatureContract $contract): bool
    {
        return $contract->canBeCanceled() && $this->manage($user, $contract);
    }

    /** Arquivar e desarquivar (05/11/2026): o setor e a Gerência, qualquer contrato. */
    public function archive(User $user, SignatureContract $contract): bool
    {
        return $this->manage($user, $contract);
    }

    /**
     * Consultar a biblioteca de cláusulas e modelos do setor: quem redige ou
     * quem cuida da biblioteca (e a Gerência).
     */
    public function viewLibrary(User $user): bool
    {
        return $user->can(P::ASSINATURA_CONTRATOS) || $user->can(P::CONTRATOS_BIBLIOTECA) || $user->isManagementMember();
    }

    /**
     * Cadastrar, editar e excluir cláusulas e modelos da biblioteca (inclui
     * "Salvar na biblioteca" e "Salvar como modelo do setor"). Permissão
     * própria desde 04/11/2026; USAR a biblioteca no editor segue de quem
     * redige (`update` do contrato).
     */
    public function manageLibrary(User $user): bool
    {
        return $user->can(P::CONTRATOS_BIBLIOTECA);
    }

    /**
     * Prestadores (05/11/2026): cadastrar, editar e inativar. Decisão do
     * usuário: quem tem acesso a Contratos — sem permissão própria.
     */
    public function manageProviders(User $user): bool
    {
        return $user->can(P::ASSINATURA_CONTRATOS) || $user->isManagementMember();
    }

    /** Criar aditivo: de quem redige pelo setor, e só de contrato já assinado (05/11/2026). */
    public function addendum(User $user, SignatureContract $contract): bool
    {
        return $contract->isSigned()
            && ($this->isSectorWriter($user, $contract) || ($user->isManagementMember() && $user->can(P::ASSINATURA_CONTRATOS)));
    }

    /**
     * Marcar/desmarcar como aditivo (05/11/2026): quem pode mexer no texto,
     * enquanto o contrato não foi aprovado.
     */
    public function changeKind(User $user, SignatureContract $contract): bool
    {
        return $contract->canChangeKind() && $this->update($user, $contract);
    }

    /** O titular da área da Presidência (quem assina pelo clube). */
    private function isPresident(User $user): bool
    {
        $presidente = app(ContractClub::class)->president();

        return $presidente !== null && $user->id !== null && (int) $presidente['user_id'] === (int) $user->id;
    }

    private function isSectorWriter(User $user, SignatureContract $contract): bool
    {
        return $user->can(P::ASSINATURA_CONTRATOS)
            && in_array((int) $contract->sector_id, $user->sectorIds(), true);
    }
}
