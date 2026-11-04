<?php

namespace App\Policies;

use App\Authorization\Permissions as P;
use App\Models\SignatureDocument;
use App\Models\User;

/**
 * Quem pode o quê com um documento de assinatura.
 *
 * São quatro permissões, e não uma, porque são quatro acessos de peso
 * diferente — ver a migration que as cria. A que mais importa é
 * `viewEvidence`: foto e traço de uma pessoa não são o mesmo dado que o
 * documento que ela assinou, e quem consulta um não precisa ver o outro.
 *
 * Quem cria documentos também os consulta (`assinatura.documentos` cobre
 * a leitura): exigir as duas permissões faria o atendente perder o próprio
 * atendimento de vista assim que o documento fosse assinado.
 *
 * Documento gerado de contrato (04/11/2026): quem tem `contratos.assinatura`
 * e é do setor do contrato (ou da Gerência)
 * conduz a assinatura como o atendente de Assinaturas: ver, editar o rascunho,
 * congelar, liberar, link remoto, gov.br, anexos, conferência, cancelar e
 * arquivar. Evidências e revisão continuam com as permissões de Assinaturas.
 */
class SignatureDocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(P::ASSINATURA_DOCUMENTOS)
            || $user->can(P::ASSINATURA_CONSULTAR)
            // Quem revisa precisa abrir o documento que confere.
            || $this->reviewAny($user);
    }

    /** Alcança a revisão interna (a comum ou a da coordenação). */
    public function reviewAny(User $user): bool
    {
        return $user->can(P::ASSINATURA_REVISAR)
            || $user->can(P::ASSINATURA_REVISAR_COORDENACAO);
    }

    /**
     * Registrar a revisão deste documento. A regra do prazo e de quem
     * acompanhou é do SignatureReviewService::blockReason — a tela diz o
     * motivo, em vez de um 403 mudo.
     */
    public function review(User $user, SignatureDocument $document): bool
    {
        return $this->reviewAny($user);
    }

    public function view(User $user, SignatureDocument $document): bool
    {
        return $this->viewAny($user) || $this->conductsContract($user, $document);
    }

    /**
     * A aba Assinaturas do módulo Contratos: a lista dos documentos dos
     * contratos que a pessoa enxerga.
     */
    public function viewContractSignatures(User $user): bool
    {
        return $user->can(P::CONTRATOS_ASSINATURA) || $user->can(P::ASSINATURA_DOCUMENTOS);
    }

    /**
     * Conduz a assinatura deste documento pelo módulo Contratos: o documento
     * veio de um contrato que a pessoa enxerga, e ela tem `contratos.assinatura`.
     */
    private function conductsContract(User $user, SignatureDocument $document): bool
    {
        if (!$user->can(P::CONTRATOS_ASSINATURA)) {
            return false;
        }

        $contrato = $document->contract;

        // O setor do contrato (ou a Gerência), como na lista da aba. Não é o
        // `view` do contrato: aquele exige também a permissão de redigir.
        return $contrato !== null
            && ($user->isManagementMember() || in_array((int) $contrato->sector_id, $user->sectorIds(), true));
    }

    public function create(User $user): bool
    {
        return $user->can(P::ASSINATURA_DOCUMENTOS);
    }

    /** Editar só existe enquanto é rascunho — a regra de estado é do model. */
    public function update(User $user, SignatureDocument $document): bool
    {
        return ($user->can(P::ASSINATURA_DOCUMENTOS) || $this->conductsContract($user, $document))
            && $document->editBlockReason() === null;
    }

    /** Congelar e liberar para o tablet. */
    public function release(User $user, SignatureDocument $document): bool
    {
        return $user->can(P::ASSINATURA_DOCUMENTOS) || $this->conductsContract($user, $document);
    }

    /**
     * Enviar o PDF que voltou assinado pelo gov.br para conferência. É ato de
     * atendimento — receber o documento de volta —, da mesma permissão de
     * quem libera a assinatura no tablet. Ver o resultado é de quem vê o
     * documento (`view`).
     */
    public function checkGovbr(User $user, SignatureDocument $document): bool
    {
        return $user->can(P::ASSINATURA_DOCUMENTOS) || $this->conductsContract($user, $document);
    }

    /**
     * Enviar e remover anexos (identidade, comprovante). Ato de atendimento,
     * da mesma permissão de quem prepara o documento; a regra de estado é do
     * SignatureAttachmentService. Ver e baixar é de quem vê o documento.
     */
    public function attach(User $user, SignatureDocument $document): bool
    {
        return $user->can(P::ASSINATURA_DOCUMENTOS) || $this->conductsContract($user, $document);
    }

    /**
     * Conferir as respostas do tablet: aprovar, devolver ou corrigir. Ato de
     * atendimento, de quem libera a assinatura (SignatureAnswerCheckService).
     */
    public function checkAnswers(User $user, SignatureDocument $document): bool
    {
        return $user->can(P::ASSINATURA_DOCUMENTOS) || $this->conductsContract($user, $document);
    }

    public function cancel(User $user, SignatureDocument $document): bool
    {
        return $user->can(P::ASSINATURA_DOCUMENTOS) || $this->conductsContract($user, $document);
    }

    /**
     * Arquivar e desarquivar: tirar da lista padrão e devolver. Só exibição,
     * em qualquer situação do documento — da mesma permissão de quem cancela.
     */
    public function hide(User $user, SignatureDocument $document): bool
    {
        return $user->can(P::ASSINATURA_DOCUMENTOS) || $this->conductsContract($user, $document);
    }

    /** Baixar o PDF (original ou final). */
    public function download(User $user, SignatureDocument $document): bool
    {
        return $this->viewAny($user) || $this->conductsContract($user, $document);
    }

    /**
     * Ver a foto do signatário e a imagem do traço. Permissão própria, a mais
     * restrita das quatro.
     */
    public function viewEvidence(User $user, SignatureDocument $document): bool
    {
        return $user->can(P::ASSINATURA_EVIDENCIAS);
    }
}
