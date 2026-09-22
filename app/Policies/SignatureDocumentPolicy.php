<?php

namespace App\Policies;

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
 * Quem cria documentos também os consulta (`manage signature documents` cobre
 * a leitura): exigir as duas permissões faria o atendente perder o próprio
 * atendimento de vista assim que o documento fosse assinado.
 */
class SignatureDocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage signature documents')
            || $user->can('view signed documents');
    }

    public function view(User $user, SignatureDocument $document): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('manage signature documents');
    }

    /** Editar só existe enquanto é rascunho — a regra de estado é do model. */
    public function update(User $user, SignatureDocument $document): bool
    {
        return $user->can('manage signature documents')
            && $document->editBlockReason() === null;
    }

    /** Congelar e liberar para o tablet. */
    public function release(User $user, SignatureDocument $document): bool
    {
        return $user->can('manage signature documents');
    }

    public function cancel(User $user, SignatureDocument $document): bool
    {
        return $user->can('manage signature documents');
    }

    /** Baixar o PDF (original ou final). */
    public function download(User $user, SignatureDocument $document): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Ver a foto do signatário e a imagem do traço. Permissão própria, a mais
     * restrita das quatro.
     */
    public function viewEvidence(User $user, SignatureDocument $document): bool
    {
        return $user->can('view signature evidences');
    }
}
