<?php

namespace App\Services\Signature\Contracts;

use App\Exceptions\ContractConflictException;
use App\Exceptions\ContractException;
use App\Exceptions\SignatureDocumentLockedException;
use App\Mail\SignatureContractCanceledMail;
use App\Mail\SignatureContractDecisionMail;
use App\Mail\SignatureContractRefusedMail;
use App\Mail\SignatureContractReviewMail;
use App\Models\SignatureAuditEvent;
use App\Models\SignatureContract;
use App\Models\SignatureContractAttachment;
use App\Models\SignatureContractComment;
use App\Models\SignatureContractEvent;
use App\Models\SignatureContractModel;
use App\Models\SignatureContractParty;
use App\Models\SignatureContractVersion;
use App\Models\SignatureDocument;
use App\Models\SignatureSigner;
use App\Services\Signature\SignatureDocumentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * O ciclo do contrato (Assinaturas → Contratos):
 *
 *     rascunho ─enviar→ em validação ─devolver→ devolvido ─enviar→ em validação …
 *                                     └aprovar→ aprovado (número + documento de assinatura em rascunho)
 *     aprovado ─reabrir→ rascunho (enquanto o documento não foi congelado)
 *     rascunho/devolvido/em validação ─cancelar→ cancelado
 *
 * Cada envio congela uma versão; a Gerência compara com a que devolveu. A
 * Gerência pode ajustar o texto direto na validação: o ajuste vira versão
 * própria (`by_reviewer`) antes da decisão, para o histórico dizer quem
 * mudou o quê.
 *
 * Quem pode cada ação é da SignatureContractPolicy; aqui ficam as regras de
 * estado. As ações recebem id e nome de quem age (e não o User): o model
 * User é preso à conexão `mysql`, e os retratos de nome são o que o
 * histórico mostra.
 */
class ContractService
{
    public function __construct(
        private ContractRenderer $renderer,
        private ContractDocumentGenerator $generator,
        private SignatureDocumentService $documents,
        private ContractRecipients $recipients,
        private ContractNotifier $notifier,
    ) {
    }

    /** "CTL - 3/26 — Título" ou só o título, para as notificações. */
    private function contractName(SignatureContract $contract): string
    {
        return ($contract->number ? $contract->number . ' — ' : '') . $contract->title;
    }

    /**
     * Contrato novo, em rascunho — em branco ou a partir de um modelo do setor.
     */
    public function create(
        int $sectorId,
        ?string $sectorName,
        ?string $title,
        ?SignatureContractModel $model,
        ?int $userId,
        ?string $userName,
        ?array $party = null,
    ): SignatureContract {
        $conteudo = $model ? $this->modelContent($model) : ContractContent::blank();

        if (filled($title)) {
            $conteudo['title'] = mb_substr(trim((string) $title), 0, 200);
        }

        if ($party !== null) {
            $conteudo = $this->withMainParty($conteudo, $party);
            $this->rememberParties($conteudo, $userName);
        }

        return DB::transaction(function () use ($sectorId, $sectorName, $conteudo, $model, $userId, $userName) {
            $contrato = SignatureContract::create([
                'sector_id' => $sectorId,
                'sector_name' => $sectorName,
                'title' => $conteudo['title'] ?: 'Contrato',
                'content' => $conteudo,
                'created_by' => $userId,
                'created_by_name' => $userName,
            ]);

            $this->event($contrato, 'created', $userId, $userName, array_filter(['modelo' => $model?->name]));

            return $contrato;
        });
    }

    /**
     * Grava a cópia de trabalho. O setor grava rascunho e devolvido; a
     * Gerência grava também durante a validação (o ajuste direto).
     *
     * Salvar automático (05/11/2026): `$expectedRevision` é a revisão do texto
     * que a tela abriu (`content_revision`). Se outra pessoa gravou depois, a
     * gravação é recusada (ContractConflictException) — o editor avisa
     * "alguém salvou antes de você" em vez de apagar o que ela gravou. `null`
     * não confere (comandos, testes antigos); `$force` grava por cima, quando
     * a pessoa escolhe isso depois do aviso.
     *
     * @param  array<string, mixed>  $input  o que veio do formulário
     *
     * @throws ContractException
     */
    public function save(
        SignatureContract $contract,
        array $input,
        ?int $userId,
        ?string $userName,
        ?int $expectedRevision = null,
        bool $force = false,
    ): SignatureContract {
        if (!$contract->isEditableBySector() && $contract->status !== SignatureContract::STATUS_IN_REVIEW) {
            throw new ContractException('Contrato ' . mb_strtolower($contract->statusLabel()) . ': o texto não muda mais.'
                . ($contract->status === SignatureContract::STATUS_APPROVED ? ' Para alterar, reabra o contrato.' : ''));
        }

        // O formulário não manda o retrato do original: ele é refeito aqui (aditivo, 05/11/2026).
        $conteudo = $this->withAddendumContext($contract, $input);

        $emValidacao = $contract->status === SignatureContract::STATUS_IN_REVIEW;

        DB::transaction(function () use ($contract, $conteudo, $userName, $expectedRevision, $force) {
            // Lida travada: dois salvando ao mesmo tempo não passam os dois pela conferência.
            $atual = SignatureContract::whereKey($contract->id)->lockForUpdate()->first(['id', 'status', 'content_revision', 'content_saved_by_name', 'content_saved_at']);

            if ($atual && $atual->status !== $contract->status) {
                throw new ContractException('O contrato mudou de situação (' . mb_strtolower($atual->statusLabel()) . ') enquanto você editava. Recarregue a página.');
            }

            if ($expectedRevision !== null && !$force && $atual && (int) $atual->content_revision !== $expectedRevision) {
                throw new ContractConflictException($atual->content_saved_by_name, $atual->content_saved_at, (int) $atual->content_revision);
            }

            $contract->forceFill([
                'content' => $conteudo,
                'title' => $conteudo['title'] ?: $contract->title,
                'content_revision' => (int) ($atual?->content_revision ?? $contract->content_revision) + 1,
                'content_saved_by_name' => $userName,
                'content_saved_at' => now(),
            ])->save();
        });

        $this->rememberParties($conteudo, $userName);

        // Ajuste da Gerência durante a validação: avisa quem redigiu (05/11/2026).
        if ($emValidacao) {
            $this->notifier->adjusted($contract, $userId, $userName);
        }

        return $contract;
    }

    /**
     * Envia à Gerência: congela a versão e avisa o coordenador.
     *
     * @throws ContractException
     */
    public function submit(SignatureContract $contract, ?string $note, ?int $userId, ?string $userName): SignatureContractVersion
    {
        if (!$contract->isEditableBySector()) {
            throw new ContractException('Só se envia à Gerência um contrato em rascunho ou devolvido.');
        }

        $this->ensureReady($contract, 'Ainda há o que corrigir antes de enviar à Gerência.');

        $versao = DB::transaction(function () use ($contract, $note, $userId, $userName) {
            $versao = $this->snapshot($contract, $userId, $userName, trim((string) $note) ?: null, false);

            $contract->forceFill([
                'status' => SignatureContract::STATUS_IN_REVIEW,
                'submitted_at' => now(),
            ])->save();

            $this->event($contract, 'submitted', $userId, $userName, ['versao' => $versao->version]);

            return $versao;
        });

        $this->mail(fn() => $this->recipients->reviewers(), new SignatureContractReviewMail($contract->fresh(), $versao));
        $this->notifier->notify($contract, 'submitted', [ContractNotifier::TO_MANAGEMENT],
            ($userName ?: 'Alguém') . ' enviou ' . $this->contractName($contract) . ' (setor ' . ($contract->sector_name ?? '—') . ') para validação.', $userId);

        return $versao;
    }

    /**
     * Devolve com observações: a geral e uma por cláusula (uid => texto).
     *
     * @param  array<string, string>  $clauseComments
     *
     * @throws ContractException
     */
    public function returnToAuthor(
        SignatureContract $contract,
        ?string $note,
        array $clauseComments,
        ?int $userId,
        ?string $userName,
    ): SignatureContractVersion {
        $this->ensureInReview($contract);

        $note = trim((string) $note) ?: null;
        $comentarios = collect($clauseComments)
            ->map(fn($texto) => trim((string) $texto))
            ->filter(fn($texto) => $texto !== '')
            ->map(fn($texto) => mb_substr($texto, 0, 3000));

        if ($note === null && $comentarios->isEmpty()) {
            throw new ContractException('Para devolver, escreva ao menos uma observação (geral ou numa cláusula).');
        }

        $uids = collect($contract->content['clauses'] ?? [])->pluck('uid')->all();

        $versao = DB::transaction(function () use ($contract, $note, $comentarios, $uids, $userId, $userName) {
            $versao = $this->reviewerVersion($contract, $userId, $userName);

            $versao->forceFill([
                'decision' => SignatureContractVersion::DECISION_RETURNED,
                'decided_by' => $userId,
                'decided_by_name' => $userName,
                'decided_at' => now(),
                'decision_note' => $note,
            ])->save();

            if ($note !== null) {
                $this->comment($contract, $versao, null, $note, $userId, $userName);
            }

            foreach ($comentarios as $uid => $texto) {
                // Observação de cláusula que não existe vira observação geral.
                $this->comment($contract, $versao, in_array($uid, $uids, true) ? (string) $uid : null, $texto, $userId, $userName);
            }

            $contract->forceFill(['status' => SignatureContract::STATUS_RETURNED])->save();

            $this->event($contract, 'returned', $userId, $userName, [
                'versao' => $versao->version,
                'observacoes' => $comentarios->count() + ($note !== null ? 1 : 0),
            ]);

            return $versao;
        });

        $this->mail(fn() => array_filter([$this->recipients->author($contract)]), new SignatureContractDecisionMail($contract->fresh(), $versao));
        $this->notifier->notify($contract, 'returned', [ContractNotifier::TO_AUTHOR],
            ($userName ?: 'A Gerência') . ' devolveu ' . $this->contractName($contract) . ' com observações para ajustar.', $userId);

        return $versao;
    }

    /**
     * Aprova: dá o número e gera o documento de assinatura em rascunho.
     *
     * O número sai aqui, e não na criação, para a sequência não ter buracos
     * de contratos desistidos. O documento é gerado DEPOIS do commit: se a
     * geração falhar, o contrato continua aprovado e o botão "Gerar documento
     * de assinatura" tenta de novo.
     *
     * @return array{contract: SignatureContract, document: ?SignatureDocument, warnings: array<int, string>}
     *
     * @throws ContractException
     */
    /**
     * `$initialsPath`: o visto que o gerente desenhou ao aprovar, já gravado
     * (ContractApprovalInitials). Vai na versão aprovada e no documento gerado.
     * Obrigatório nas telas (computador e tablet); aqui é opcional para o
     * serviço servir também a quem aprova por código (testes, comandos).
     */
    public function approve(SignatureContract $contract, ?string $note, ?int $userId, ?string $userName, ?string $initialsPath = null): array
    {
        $this->ensureInReview($contract);
        $this->ensureReady($contract, 'O contrato ainda tem o que corrigir. Ajuste ou devolva antes de aprovar.');

        $versao = DB::transaction(function () use ($contract, $note, $userId, $userName, $initialsPath) {
            $versao = $this->reviewerVersion($contract, $userId, $userName);

            $versao->forceFill([
                'decision' => SignatureContractVersion::DECISION_APPROVED,
                'decided_by' => $userId,
                'decided_by_name' => $userName,
                'decided_at' => now(),
                'decision_note' => trim((string) $note) ?: null,
                'approval_initials_path' => $initialsPath,
            ])->save();

            $this->assignNumber($contract);

            $contract->forceFill([
                'status' => SignatureContract::STATUS_APPROVED,
                'approved_version_id' => $versao->id,
                'approved_at' => now(),
                'approved_by' => $userId,
                'approved_by_name' => $userName,
            ])->save();

            $this->event($contract, 'approved', $userId, $userName, [
                'versao' => $versao->version,
                'numero' => $contract->number,
                'visto' => $initialsPath !== null,
            ]);

            return $versao;
        });

        $resultado = $this->generateDocument($contract->fresh(), $userId, $userName);

        $this->mail(fn() => array_filter([$this->recipients->author($contract)]), new SignatureContractDecisionMail($contract->fresh(), $versao));
        $this->notifier->notify($contract->fresh(), 'approved', [ContractNotifier::TO_AUTHOR],
            ($userName ?: 'A Gerência') . ' aprovou ' . $this->contractName($contract->fresh()) . '. O documento de assinatura foi gerado.', $userId);

        return ['contract' => $contract->fresh()] + $resultado;
    }

    /**
     * Gera (de novo) o documento de assinatura de um contrato aprovado — o
     * da aprovação falhou, ou foi cancelado no módulo de assinatura.
     *
     * @return array{document: ?SignatureDocument, warnings: array<int, string>}
     */
    public function generateDocument(SignatureContract $contract, ?int $userId, ?string $userName): array
    {
        if ($contract->status !== SignatureContract::STATUS_APPROVED) {
            throw new ContractException('Só contrato aprovado gera documento de assinatura.');
        }

        $atual = $contract->document;

        // Recusado por uma testemunha ou um fiador (a recusa do presidente e da
        // contratada devolve o contrato): gera-se outro, do zero.
        if ($atual && !in_array($atual->status, [SignatureDocument::STATUS_CANCELED, SignatureDocument::STATUS_REFUSED], true)) {
            throw new ContractException('Este contrato já tem documento de assinatura em andamento.');
        }

        try {
            ['document' => $documento, 'warnings' => $avisos] = $this->generator->generate($contract, $userId, $userName);
        } catch (\Throwable $e) {
            Log::error('Contrato aprovado sem documento de assinatura: a geração falhou.', [
                'contrato' => $contract->id,
                'erro' => $e->getMessage(),
            ]);

            return ['document' => null, 'warnings' => [
                'O contrato foi aprovado, mas o documento de assinatura não foi gerado (' . $e->getMessage()
                    . '). Tente de novo pelo botão "Gerar documento de assinatura".',
            ]];
        }

        $contract->forceFill(['signature_document_id' => $documento->id])->save();

        $this->event($contract, 'document_generated', $userId, $userName, [
            'documento' => $documento->id,
            'avisos' => count($avisos),
        ]);

        return ['document' => $documento, 'warnings' => $avisos];
    }

    /**
     * Reabre um contrato aprovado para alteração. Só enquanto o documento de
     * assinatura não foi congelado: depois disso alguém já pode ter lido e
     * assinado aquele texto. O rascunho do documento é cancelado; o número
     * fica (é o mesmo contrato).
     *
     * @throws ContractException
     */
    public function reopen(SignatureContract $contract, ?int $userId, ?string $userName): SignatureContract
    {
        if ($contract->status !== SignatureContract::STATUS_APPROVED) {
            throw new ContractException('Só se reabre contrato aprovado.');
        }

        $documento = $contract->document;

        if ($documento && $documento->isFrozen()
            && !in_array($documento->status, [SignatureDocument::STATUS_CANCELED, SignatureDocument::STATUS_REFUSED], true)) {
            throw new ContractException('O documento de assinatura já foi congelado: o texto pode ter sido lido e assinado. '
                . 'Cancele o documento em Assinaturas → Documentos antes de reabrir, ou faça um aditivo.');
        }

        return DB::transaction(function () use ($contract, $documento, $userId, $userName) {
            if ($documento && $documento->status === SignatureDocument::STATUS_DRAFT) {
                try {
                    $this->documents->cancel($documento, 'Contrato reaberto para alteração', $userId);
                } catch (SignatureDocumentLockedException $e) {
                    throw new ContractException($e->getMessage());
                }
            }

            $contract->forceFill([
                'status' => SignatureContract::STATUS_DRAFT,
                'signature_document_id' => null,
                'approved_version_id' => null,
                'approved_at' => null,
                'approved_by' => null,
                'approved_by_name' => null,
            ])->save();

            $this->event($contract, 'reopened', $userId, $userName, ['documento_cancelado' => $documento?->id]);

            return $contract;
        });
    }

    /*
     | Recusa na assinatura (05/11/2026, pedido do usuário)
     |
     | O presidente ou a contratada pode não concordar com o texto na hora de
     | assinar. A recusa deles devolve o contrato a quem redigiu (situação
     | "devolvido"), com o motivo como observação; o documento recusado fica
     | encerrado e, aprovado de novo, nasce um documento novo — as assinaturas
     | recomeçam do zero. O número fica. A recusa de testemunha ou fiador só
     | encerra o documento: o contrato continua aprovado e dá para gerar outro
     | documento (decisão do usuário: só presidente e contratada devolvem).
     |
     | Onde se recusa: no tablet (balcão e Assinatura Diretoria, documento por
     | documento) e no link remoto — os dois chegam aqui pelo
     | SignatureCaptureService::refuse — e, o presidente, também na tela do
     | contrato no Lara (refuseByPresident).
     */

    /**
     * Quem é o signatário para o contrato: `presidente`, `contratada` ou
     * null (testemunha, fiador, outro diretor).
     */
    public function refusalRole(SignatureContract $contract, SignatureSigner $signer): ?string
    {
        $entrada = collect($this->renderer->documentSigners($contract->content ?? []))
            ->firstWhere('key', (string) $signer->party);

        if ($entrada && !empty($entrada['president'])) {
            return 'presidente';
        }

        // A área da Presidência vale mesmo se a ordem das partes mudou (presidente trocado no rascunho).
        if ($signer->isDirector() && $signer->director_area_name === (string) config('signature.contracts.president_area')) {
            return 'presidente';
        }

        return $entrada && $entrada['party'] === ContractContent::SIGNER_CONTRATADA ? 'contratada' : null;
    }

    /**
     * Um signatário recusou o documento de um contrato (tablet ou link
     * remoto, chamado DEPOIS do commit da recusa). Devolve true se o
     * contrato voltou para revisão.
     */
    public function signatureRefused(SignatureDocument $document, SignatureSigner $signer, ?string $reason): bool
    {
        $contrato = SignatureContract::where('signature_document_id', $document->id)->first();

        if (!$contrato || $contrato->status !== SignatureContract::STATUS_APPROVED) {
            return false;
        }

        $papel = $this->refusalRole($contrato, $signer);

        if ($papel === null) {
            $this->event($contrato, 'signature_refused', null, $signer->name, array_filter([
                'documento' => $document->id,
                'por' => $signer->name,
                'motivo' => trim((string) $reason) ?: null,
            ]));

            return false;
        }

        DB::transaction(fn() => $this->returnFromSignature($contrato, $document, $signer->name, $papel, $reason, null));

        $this->mailRefusal($contrato, $signer->name, $reason);

        return true;
    }

    /**
     * O signatário que o presidente é neste contrato, ainda pendente — o
     * que a tela do contrato deixa recusar. Null se não há.
     */
    public function presidentSignerFor(SignatureContract $contract, int $userId): ?SignatureSigner
    {
        $documento = $contract->status === SignatureContract::STATUS_APPROVED ? $contract->document : null;

        if (!$documento || !in_array($documento->status, [SignatureDocument::STATUS_DRAFT, SignatureDocument::STATUS_AWAITING_SIGNATURE], true)) {
            return null;
        }

        return $documento->signers()
            ->where('user_id', $userId)
            ->where('status', SignatureSigner::STATUS_PENDING)
            ->get()
            ->first(fn(SignatureSigner $s) => $this->refusalRole($contract, $s) === 'presidente');
    }

    /**
     * O presidente recusa pela tela do contrato no Lara. Com o documento já
     * congelado, a recusa é registrada no documento como a do tablet; em
     * rascunho (ainda ninguém assinou), o rascunho é cancelado.
     *
     * @throws ContractException
     */
    public function refuseByPresident(SignatureContract $contract, string $reason, int $userId, string $userName): void
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new ContractException('Escreva o motivo da recusa: é ele que orienta a revisão do contrato.');
        }

        $signatario = $this->presidentSignerFor($contract, $userId);

        if (!$signatario) {
            throw new ContractException('Este contrato não está aguardando a sua assinatura.');
        }

        $documento = $contract->document;
        $estados = app(\App\Services\Signature\SignatureStateMachine::class);

        DB::transaction(function () use ($contract, $documento, $signatario, $reason, $userId, $userName, $estados) {
            if ($documento->status === SignatureDocument::STATUS_DRAFT) {
                try {
                    $this->documents->cancel($documento, 'Recusado pelo presidente: ' . mb_substr($reason, 0, 200), $userId);
                } catch (SignatureDocumentLockedException $e) {
                    throw new ContractException($e->getMessage());
                }
            } else {
                $contexto = ['actor_type' => SignatureAuditEvent::ACTOR_USER, 'actor_id' => $userId];

                $estados->signerTo(
                    $signatario,
                    SignatureSigner::STATUS_REFUSED,
                    SignatureAuditEvent::EVENT_REFUSED,
                    ['refused_at' => now(), 'refusal_reason' => mb_substr($reason, 0, 255)],
                    $contexto + ['payload' => ['motivo' => $reason, 'onde' => 'tela do contrato no Lara']],
                );

                $estados->documentTo(
                    $documento,
                    SignatureDocument::STATUS_REFUSED,
                    SignatureAuditEvent::EVENT_REFUSED,
                    [],
                    $contexto + ['signer' => $signatario->id],
                );
            }

            $this->returnFromSignature($contract, $documento, $userName, 'presidente', $reason, $userId);
        });

        $this->mailRefusal($contract->fresh(), $userName, $reason);
    }

    /** Volta o contrato para quem redigiu, com o motivo da recusa como observação. */
    private function returnFromSignature(
        SignatureContract $contract,
        SignatureDocument $document,
        string $refusedBy,
        string $role,
        ?string $reason,
        ?int $userId,
    ): void {
        $motivo = trim((string) $reason) ?: null;
        $versao = $contract->approved_version_id
            ? SignatureContractVersion::find($contract->approved_version_id)
            : $contract->latestVersion();

        if ($versao) {
            $this->comment(
                $contract,
                $versao,
                null,
                'Recusado na assinatura por ' . $refusedBy . ' (' . ($role === 'presidente' ? 'presidente' : 'contratada') . '): '
                    . ($motivo ?? 'sem motivo informado.'),
                $userId,
                $refusedBy,
            );
        }

        $contract->forceFill([
            'status' => SignatureContract::STATUS_RETURNED,
            'signature_document_id' => null,
            'approved_version_id' => null,
            'approved_at' => null,
            'approved_by' => null,
            'approved_by_name' => null,
        ])->save();

        $this->event($contract, 'signature_refused', $userId, $refusedBy, array_filter([
            'documento' => $document->id,
            'por' => $refusedBy,
            'papel' => $role,
            'motivo' => $motivo,
            'devolvido' => true,
        ]));
    }

    private function mailRefusal(SignatureContract $contract, string $refusedBy, ?string $reason): void
    {
        $this->mail(
            fn() => array_filter([$this->recipients->author($contract)]),
            new SignatureContractRefusedMail($contract, $refusedBy, trim((string) $reason) ?: null),
        );

        // Notificação (05/11/2026): quem redigiu e a Gerência.
        $this->notifier->notify($contract, 'refused', [ContractNotifier::TO_AUTHOR, ContractNotifier::TO_MANAGEMENT],
            $refusedBy . ' recusou a assinatura de ' . $this->contractName($contract) . '; ele voltou para revisão.'
                . (trim((string) $reason) !== '' ? ' Motivo: ' . mb_substr(trim((string) $reason), 0, 150) : ''));
    }

    /**
     * @throws ContractException
     */
    /**
     * Cancelar (05/11/2026, decisões do usuário): em qualquer fase ATÉ A
     * ASSINATURA TERMINAR — aprovado e em assinatura também, e aí o documento
     * de assinatura é cancelado junto (quem ainda não assinou não assina
     * mais). Assinado não se cancela. Motivo obrigatório: vai para o
     * histórico e, por e-mail, para quem redigiu.
     *
     * @throws ContractException
     */
    public function cancel(SignatureContract $contract, ?string $reason, ?int $userId, ?string $userName): SignatureContract
    {
        $reason = trim((string) $reason);

        if ($contract->status === SignatureContract::STATUS_CANCELED) {
            throw new ContractException('O contrato já está cancelado.');
        }

        if (!$contract->canBeCanceled()) {
            throw new ContractException('Contrato já assinado não se cancela: para mudar ou encerrar, faça um aditivo.');
        }

        if ($reason === '') {
            throw new ContractException('Informe o motivo do cancelamento.');
        }

        DB::transaction(function () use ($contract, $reason, $userId, $userName) {
            $documento = $contract->document;
            $docCancelado = false;

            if ($documento && in_array($documento->status, [SignatureDocument::STATUS_DRAFT, SignatureDocument::STATUS_AWAITING_SIGNATURE], true)) {
                try {
                    $this->documents->cancel($documento, 'Contrato cancelado: ' . mb_substr($reason, 0, 200), $userId);
                    $docCancelado = true;
                } catch (SignatureDocumentLockedException $e) {
                    throw new ContractException($e->getMessage());
                }
            }

            $contract->forceFill(['status' => SignatureContract::STATUS_CANCELED, 'canceled_at' => now()])->save();

            $this->event($contract, 'canceled', $userId, $userName, array_filter([
                'motivo' => $reason,
                'documento_cancelado' => $docCancelado ?: null,
            ]));
        });

        $this->mail(
            fn() => array_filter([$this->recipients->author($contract)]),
            new SignatureContractCanceledMail($contract, (string) $userName, $reason),
        );
        $this->notifier->notify($contract, 'canceled', [ContractNotifier::TO_AUTHOR, ContractNotifier::TO_MANAGEMENT],
            ($userName ?: 'Alguém') . ' cancelou ' . $this->contractName($contract) . '. Motivo: ' . mb_substr($reason, 0, 150), $userId);

        return $contract;
    }

    /**
     * Arquivar (05/11/2026, decisão do usuário): só tira das abas do dia a
     * dia — qualquer contrato, em qualquer situação, nada muda nele.
     * Motivo obrigatório (histórico).
     *
     * @throws ContractException
     */
    public function archive(SignatureContract $contract, ?string $reason, ?int $userId, ?string $userName): SignatureContract
    {
        $reason = trim((string) $reason);

        if ($contract->isArchived()) {
            throw new ContractException('O contrato já está arquivado.');
        }

        if ($reason === '') {
            throw new ContractException('Informe o motivo para arquivar.');
        }

        $contract->forceFill(['archived_at' => now(), 'archived_by_name' => $userName])->save();
        $this->event($contract, 'archived', $userId, $userName, ['motivo' => $reason]);

        return $contract;
    }

    /**
     * O documento de assinatura do contrato terminou de ser assinado
     * (SignatureStateMachine, ao ir para "assinado"; 05/11/2026): grava a
     * data, calcula o término da vigência (prazo a partir da assinatura ou
     * data fixa) e avisa quem redigiu e a Gerência.
     */
    public function documentSigned(SignatureDocument $document): void
    {
        $contrato = SignatureContract::where('signature_document_id', $document->id)->first();

        if (!$contrato || $contrato->status !== SignatureContract::STATUS_APPROVED || $contrato->signed_at !== null) {
            return;
        }

        $assinadoEm = now();
        $termino = $contrato->expiryFrom($assinadoEm);

        $contrato->forceFill([
            'signed_at' => $assinadoEm,
            'expires_on' => $termino?->toDateString(),
            'expiry_alerts' => null,
        ])->save();

        $this->event($contrato, 'signed', null, null, array_filter(['termino' => $termino?->format('d/m/Y')]));

        $this->notifier->notify($contrato, 'signed', [ContractNotifier::TO_AUTHOR, ContractNotifier::TO_MANAGEMENT],
            'Todos assinaram ' . $this->contractName($contrato) . '.'
                . ($termino ? ' Vigência até ' . $termino->format('d/m/Y') . '.' : ''));
    }

    /**
     * Informar ou mudar a vigência de um contrato JÁ ASSINADO (05/11/2026):
     * os assinados antes de existir a vigência e a correção de um prazo. Só
     * o campo de vigência muda — o texto e o documento assinado ficam. O
     * término é recalculado pela data em que foi assinado (ou, nos antigos,
     * pela do documento), e os avisos recomeçam.
     *
     * @param  array{months?: mixed, until?: mixed}  $validity
     *
     * @throws ContractException
     */
    public function setValidity(SignatureContract $contract, array $validity, ?int $userId, ?string $userName): SignatureContract
    {
        if (!$contract->isSigned()) {
            throw new ContractException('A vigência de um contrato ainda não assinado se informa no editor (Dados gerais).');
        }

        $conteudo = $contract->content ?? [];
        $conteudo['validity'] = ContractContent::validity($validity);

        $assinadoEm = $contract->signed_at
            ?? $contract->document?->signers()->max('signed_at')
            ?? $contract->document?->updated_at
            ?? now();
        $assinadoEm = \Illuminate\Support\Carbon::parse($assinadoEm);

        $contract->forceFill(['content' => $conteudo, 'signed_at' => $assinadoEm])->save();

        $termino = $contract->expiryFrom($assinadoEm);
        $contract->forceFill(['expires_on' => $termino?->toDateString(), 'expiry_alerts' => null])->save();

        $this->event($contract, 'validity_changed', $userId, $userName, array_filter(['termino' => $termino?->format('d/m/Y')]));

        return $contract;
    }

    /**
     * Avisos de término (comando diário `contratos:avisar-vencimento`,
     * 05/11/2026): a 60, 30 e 15 dias do fim, para quem redigiu e o setor do
     * contrato. Cada marco vai uma vez só; quem passou de vários de uma vez
     * (vigência curta, comando parado) recebe só o mais próximo. Cancelados
     * e arquivados não avisam.
     *
     * @return int quantos contratos foram avisados
     */
    public function sendExpiryAlerts(): int
    {
        $avisados = 0;
        $limite = now()->addDays(max(SignatureContract::EXPIRY_ALERT_DAYS))->toDateString();

        SignatureContract::query()
            ->whereNotNull('expires_on')
            ->where('status', SignatureContract::STATUS_APPROVED)
            ->archived(false)
            ->whereDate('expires_on', '<=', $limite)
            ->whereDate('expires_on', '>=', now()->toDateString())
            ->orderBy('id')
            ->each(function (SignatureContract $contrato) use (&$avisados) {
                $dias = $contrato->daysToExpiry();
                $enviados = array_map('intval', $contrato->expiry_alerts ?? []);
                $devidos = array_values(array_filter(SignatureContract::EXPIRY_ALERT_DAYS, fn(int $d) => $dias <= $d));
                $novos = array_diff($devidos, $enviados);

                if ($novos === []) {
                    return;
                }

                $contrato->forceFill(['expiry_alerts' => array_values(array_unique(array_merge($enviados, $devidos)))])->save();

                $this->notifier->notify($contrato, 'expiring', [ContractNotifier::TO_AUTHOR, ContractNotifier::TO_SECTOR],
                    $this->contractName($contrato) . ' termina em ' . $contrato->expires_on->format('d/m/Y')
                        . ' (' . ($dias === 0 ? 'hoje' : ($dias === 1 ? 'amanhã' : 'faltam ' . $dias . ' dias')) . ').'
                        . ' Se for continuar, prepare o aditivo.');

                $avisados++;
            });

        return $avisados;
    }

    /** Desarquivar: volta para as abas, como estava. */
    public function unarchive(SignatureContract $contract, ?int $userId, ?string $userName): SignatureContract
    {
        if (!$contract->isArchived()) {
            throw new ContractException('O contrato não está arquivado.');
        }

        $contract->forceFill(['archived_at' => null, 'archived_by_name' => null])->save();
        $this->event($contract, 'unarchived', $userId, $userName);

        return $contract;
    }

    /*
     | Aditivos (05/11/2026, decisões do usuário)
     |
     | - Só de contrato JÁ ASSINADO (documento assinado ou finalizado).
     | - Nasce pelo "Criar aditivo" da tela do contrato (vincula sozinho) ou
     |   pela opção "Aditivo" no Novo contrato; um contrato em redação também
     |   pode ser marcado como aditivo (e desmarcado) antes da aprovação.
     | - Herda partes, CNPJ, termos e variáveis da BASE — o último aditivo
     |   assinado ou, sem ele, o contrato principal — e, dos signatários, só
     |   quem assina pela contratada (o presidente entra sozinho; fiador e
     |   testemunhas, quem redige escolhe de novo).
     | - Marcar um rascunho existente SÓ COMPLETA: o que já está nele fica.
     | - O texto pode citar a base com [[original:chave]]; o cabeçalho vira
     |   "1º TERMO ADITIVO AO CONTRATO CTL - 12/26 DE PRESTAÇÃO DE SERVIÇO…".
     | - Número: o do original + A1, A2… (na aprovação).
     */

    /**
     * Aditivo novo de um contrato assinado. Aditivo de aditivo é aditivo do
     * contrato principal.
     *
     * @throws ContractException
     */
    public function createAddendum(SignatureContract $contract, ?int $userId, ?string $userName, ?string $title = null): SignatureContract
    {
        if (!$contract->isSigned()) {
            throw new ContractException('Aditivo só de contrato já assinado.');
        }

        $original = $contract->isAddendum() && $contract->parent ? $contract->parent : $contract;
        $base = $this->addendumBase($original)->content ?? [];

        $conteudo = $this->inheritedContent($base, [
            'title' => filled($title) ? mb_substr(trim((string) $title), 0, 200) : 'Termo aditivo ao contrato ' . $original->number,
            'considerations' => [],
            'clauses' => [
                ['title' => 'Do objeto do aditivo', 'items' => [['level' => 1, 'html' => '']]],
            ],
        ]);

        return DB::transaction(function () use ($original, $conteudo, $userId, $userName) {
            $aditivo = SignatureContract::create([
                'sector_id' => $original->sector_id,
                'sector_name' => $original->sector_name,
                'kind' => SignatureContract::KIND_ADDENDUM,
                'parent_id' => $original->id,
                'title' => $conteudo['title'],
                'content' => $conteudo,
                'created_by' => $userId,
                'created_by_name' => $userName,
            ]);

            $aditivo->forceFill(['content' => $this->withAddendumContext($aditivo, $conteudo)])->save();

            $this->event($aditivo, 'created', $userId, $userName, ['aditivo_de' => $original->number]);
            $this->event($original, 'addendum_created', $userId, $userName, ['aditivo' => $aditivo->id]);

            return $aditivo;
        });
    }

    /**
     * Marca um contrato em redação como aditivo de um contrato assinado. Só
     * completa: entram as partes, variáveis e o signatário da contratada que
     * faltam; o que já está no contrato fica.
     *
     * @throws ContractException
     */
    public function linkAddendum(SignatureContract $contract, SignatureContract $target, ?int $userId, ?string $userName): SignatureContract
    {
        if (!$contract->canChangeKind()) {
            throw new ContractException('Só dá para marcar como aditivo antes da aprovação.');
        }

        if (!$target->isSigned()) {
            throw new ContractException('Aditivo só de contrato já assinado.');
        }

        $original = $target->isAddendum() && $target->parent ? $target->parent : $target;

        if ($original->id === $contract->id) {
            throw new ContractException('Um contrato não pode ser aditivo dele mesmo.');
        }

        $base = $this->addendumBase($original)->content ?? [];

        return DB::transaction(function () use ($contract, $original, $base, $userId, $userName) {
            $contract->forceFill([
                'kind' => SignatureContract::KIND_ADDENDUM,
                'parent_id' => $original->id,
            ]);

            $conteudo = $this->completeFrom($contract->content ?? ContractContent::blank(), $base);

            $contract->forceFill([
                'content' => $this->withAddendumContext($contract, $conteudo),
                'content_revision' => (int) $contract->content_revision + 1,
                'content_saved_by_name' => $userName,
                'content_saved_at' => now(),
            ])->save();

            $this->event($contract, 'linked_addendum', $userId, $userName, ['aditivo_de' => $original->number]);
            $this->event($original, 'addendum_created', $userId, $userName, ['aditivo' => $contract->id]);

            return $contract;
        });
    }

    /**
     * Desfaz o vínculo (antes da aprovação): volta a ser contrato comum. O que
     * foi herdado fica; [[original:…]] passa a ser problema na Conferência.
     *
     * @throws ContractException
     */
    public function unlinkAddendum(SignatureContract $contract, ?int $userId, ?string $userName): SignatureContract
    {
        if (!$contract->isAddendum() || !$contract->canChangeKind()) {
            throw new ContractException('Só dá para desmarcar o aditivo antes da aprovação.');
        }

        $antes = $contract->parent?->number;

        $conteudo = $contract->content ?? [];
        $conteudo['addendum'] = null;

        $contract->forceFill([
            'kind' => SignatureContract::KIND_CONTRACT,
            'parent_id' => null,
            'content' => ContractContent::normalize($conteudo),
            'content_revision' => (int) $contract->content_revision + 1,
            'content_saved_by_name' => $userName,
            'content_saved_at' => now(),
        ])->save();

        $this->event($contract, 'unlinked_addendum', $userId, $userName, ['era_aditivo_de' => $antes]);

        return $contract;
    }

    /**
     * A base do aditivo: o último aditivo ASSINADO do contrato principal ou,
     * sem nenhum, o próprio principal (decisão do usuário, 05/11/2026).
     */
    public function addendumBase(SignatureContract $original, ?int $exceptId = null): SignatureContract
    {
        $ultimo = SignatureContract::query()
            ->where('parent_id', $original->id)
            ->where('kind', SignatureContract::KIND_ADDENDUM)
            ->where('status', SignatureContract::STATUS_APPROVED)
            ->when($exceptId, fn($q) => $q->where('id', '!=', $exceptId))
            ->whereHas('document', fn($d) => $d->whereIn('status', [SignatureDocument::STATUS_SIGNED, SignatureDocument::STATUS_FINALIZED]))
            ->orderByDesc('addendum_seq')
            ->orderByDesc('id')
            ->first();

        return $ultimo ?? $original;
    }

    /**
     * Põe no conteúdo o retrato do original (cabeçalho e [[original:…]]) —
     * ou tira, se não é aditivo. Chamado a cada gravação: a base pode mudar
     * (outro aditivo assinado) e a ordem sai na aprovação.
     *
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    public function withAddendumContext(SignatureContract $contract, array $content): array
    {
        $original = $contract->isAddendum() && $contract->parent_id ? SignatureContract::find($contract->parent_id) : null;

        if (!$original || !$original->number) {
            $content['addendum'] = null;

            return ContractContent::normalize($content);
        }

        $base = $this->addendumBase($original, $contract->id);
        $ordem = $contract->addendum_seq
            ?: (int) SignatureContract::where('parent_id', $original->id)->max('addendum_seq') + 1;

        $content['addendum'] = [
            'parent_id' => $original->id,
            'parent_number' => $original->number,
            'ordinal' => $ordem,
            'base_number' => $base->number ?? $original->number,
            'variables' => $base->content['variables'] ?? [],
        ];

        return ContractContent::normalize($content);
    }

    /**
     * O conteúdo de um aditivo novo a partir da base: partes, CNPJ, termos e
     * variáveis; dos signatários, só quem assina pela contratada.
     *
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $troca
     * @return array<string, mixed>
     */
    private function inheritedContent(array $base, array $troca): array
    {
        $semUid = fn(array $linha) => ['uid' => null] + $linha;

        return ContractContent::normalize(array_merge([
            'establishment' => $base['establishment'] ?? null,
            'contractor_term' => $base['contractor_term'] ?? null,
            'parties' => array_map($semUid, $base['parties'] ?? []),
            'variables' => $base['variables'] ?? [],
            'signers' => array_map($semUid, $this->contractorSigners($base)),
        ], $troca));
    }

    /**
     * Só completa (marcar um rascunho como aditivo): partes que faltam (pelo
     * documento; parte em branco é trocada), variáveis que faltam (pela chave)
     * e quem assina pela contratada, se ninguém foi indicado.
     *
     * @param  array<string, mixed>  $content
     * @param  array<string, mixed>  $base
     * @return array<string, mixed>
     */
    private function completeFrom(array $content, array $base): array
    {
        $vazia = fn(array $p) => trim((string) ($p['name'] ?? '')) === '' && trim((string) ($p['document'] ?? '')) === '';
        $partes = array_values(array_filter($content['parties'] ?? [], fn(array $p) => !$vazia($p)));
        $documentos = array_column($partes, 'document');

        foreach ($base['parties'] ?? [] as $parte) {
            if (!in_array($parte['document'], $documentos, true)) {
                $partes[] = ['uid' => null] + $parte;
            }
        }

        $variaveis = $content['variables'] ?? [];
        $chaves = array_column($variaveis, 'key');

        foreach ($base['variables'] ?? [] as $variavel) {
            if (!in_array($variavel['key'], $chaves, true)) {
                $variaveis[] = $variavel;
            }
        }

        $signatarios = $content['signers'] ?? [];
        $temContratada = collect($signatarios)->contains(fn(array $s) => $s['party'] === ContractContent::SIGNER_CONTRATADA
            && ($s['source'] !== 'person' || trim((string) $s['name']) !== ''));

        if (!$temContratada) {
            $signatarios = array_values(array_filter($signatarios, fn(array $s) => $s['party'] !== ContractContent::SIGNER_CONTRATADA));
            $signatarios = array_merge(array_map(fn(array $s) => ['uid' => null] + $s, $this->contractorSigners($base)), $signatarios);
        }

        $content['parties'] = $partes;
        $content['variables'] = $variaveis;
        $content['signers'] = $signatarios;

        return ContractContent::normalize($content);
    }

    /** Os signatários da contratada (sem fiador, testemunha nem outros do clube). */
    private function contractorSigners(array $content): array
    {
        return array_values(array_filter(
            $content['signers'] ?? [],
            fn(array $s) => ($s['party'] ?? null) === ContractContent::SIGNER_CONTRATADA,
        ));
    }

    /** Marca a observação como resolvida (ou a reabre). */
    public function resolveComment(SignatureContractComment $comment, bool $resolved, ?int $userId, ?string $userName): void
    {
        $comment->forceFill([
            'resolved_at' => $resolved ? now() : null,
            'resolved_by_name' => $resolved ? $userName : null,
        ])->save();

        if ($resolved) {
            $this->event($comment->contract, 'comment_resolved', $userId, $userName, ['observacao' => $comment->id]);
        }
    }

    /**
     * Salva o contrato como modelo do setor: sem os dados das partes, sem
     * signatários e com as variáveis sem valor — o que é deste contrato não
     * vai para o próximo.
     */
    public function saveAsModel(SignatureContract $contract, string $name, ?int $userId, ?string $userName): SignatureContractModel
    {
        $conteudo = $contract->content ?? [];

        // Do contrato, o modelo não leva quem assina nem a vigência (só a edição do modelo os põe).
        $modelo = $this->modelOnly([
            'title' => $conteudo['title'] ?? $contract->title,
            'signers' => [],
            'validity' => null,
        ] + $conteudo);

        return SignatureContractModel::create([
            'sector_id' => $contract->sector_id,
            'sector_name' => $contract->sector_name,
            'name' => mb_substr(trim($name), 0, 150),
            'content' => $modelo,
            'created_by' => $userId,
            'created_by_name' => $userName,
        ]);
    }

    /**
     * Edição do modelo pela Biblioteca (06/11/2026): o mesmo editor do
     * contrato, gravando por cima do modelo. Passa pela mesma régua do
     * "Salvar como modelo" (modelOnly): nada de dados de pessoa nem de valor.
     * Os contratos já feitos com o modelo não mudam — cada um tem a sua cópia.
     */
    public function updateModel(SignatureContractModel $model, string $name, array $input, ?string $userName): SignatureContractModel
    {
        $model->update([
            'name' => mb_substr(trim($name), 0, 150),
            'content' => $this->modelOnly(ContractContent::normalize($input)),
            'updated_by_name' => $userName !== null ? mb_substr($userName, 0, 120) : null,
        ]);

        return $model;
    }

    /**
     * O que um modelo guarda de um conteúdo: estrutura, termos e texto. Das
     * partes, só papel, termo e tipo; das variáveis, sem preenchimento; de
     * quem assina, o papel, o cargo e o diretor (se escolhido) — sem nome,
     * CPF, e-mail ou telefone; da vigência, só o prazo em meses (data fixa
     * venceria no modelo).
     */
    private function modelOnly(array $conteudo): array
    {
        return ContractContent::normalize([
            'title' => $conteudo['title'] ?? '',
            'establishment' => $conteudo['establishment'] ?? null,
            'contractor_term' => $conteudo['contractor_term'] ?? null,
            'parties' => array_map(fn(array $p) => [
                'uid' => $p['uid'] ?? null,
                'role' => $p['role'] ?? null,
                'term' => $p['term'] ?? null,
                'kind' => $p['kind'] ?? null,
            ], $conteudo['parties'] ?? []),
            'variables' => array_map(fn(array $v) => ['key' => $v['key'], 'label' => $v['label'], 'type' => $v['type'], 'value' => ''], $conteudo['variables'] ?? []),
            'considerations' => $conteudo['considerations'] ?? [],
            'clauses' => $conteudo['clauses'] ?? [],
            'signers' => array_map(fn(array $s) => ($s['source'] ?? 'person') === 'director'
                ? array_intersect_key($s, array_flip(['uid', 'party', 'source', 'area_id', 'user_id', 'name', 'caption']))
                : ['uid' => $s['uid'] ?? null, 'party' => $s['party'] ?? null, 'source' => 'person', 'caption' => $s['caption'] ?? ''], $conteudo['signers'] ?? []),
            'validity' => ['months' => $conteudo['validity']['months'] ?? null, 'until' => null],
        ]);
    }

    /**
     * O conteúdo de um contrato novo a partir do modelo: cópia, com uid novo
     * em tudo — dois contratos do mesmo modelo não compartilham cláusula.
     */
    public function modelContent(SignatureContractModel $model): array
    {
        $conteudo = $model->content ?? [];

        $conteudo['clauses'] = ContractContent::freshUids($conteudo['clauses'] ?? []);
        $semUid = fn(array $linha) => ['uid' => null] + $linha;
        $conteudo['parties'] = array_map(fn(array $p) => $semUid($p), $conteudo['parties'] ?? []);
        $conteudo['signers'] = array_map(fn(array $s) => $semUid($s), $conteudo['signers'] ?? []);

        if (($conteudo['signers'] ?? []) === []) {
            $conteudo['signers'] = [['party' => ContractContent::SIGNER_CONTRATADA, 'source' => 'person']];
        }

        return ContractContent::normalize($conteudo);
    }

    /**
     * Anexo do contrato (PDF, JPG, PNG) — segue para o documento na aprovação.
     *
     * @throws ContractException
     */
    public function addAttachment(SignatureContract $contract, UploadedFile $file, string $label, ?int $userId, ?string $userName): SignatureContractAttachment
    {
        $this->ensureAttachmentsOpen($contract);

        $bytes = (string) file_get_contents($file->getRealPath());
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: '';
        $extensoes = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];

        if (!isset($extensoes[$mime])) {
            throw new ContractException('Envie PDF, JPG ou PNG.');
        }

        $hash = hash('sha256', $bytes);
        $caminho = 'signature/contracts/' . $contract->id . '/anexos/' . now()->format('Ymd-His') . '-' . substr($hash, 0, 12) . '.' . $extensoes[$mime];

        Storage::disk(config('signature.disk'))->put($caminho, $bytes);

        $anexo = SignatureContractAttachment::create([
            'signature_contract_id' => $contract->id,
            'label' => mb_substr(trim($label) ?: 'Anexo', 0, 120),
            'original_name' => mb_substr($file->getClientOriginalName() ?: 'arquivo', 0, 191),
            'path' => $caminho,
            'mime' => $mime,
            'bytes' => strlen($bytes),
            'sha256' => $hash,
            'uploaded_by' => $userId,
            'uploaded_by_name' => $userName,
        ]);

        $this->event($contract, 'attachment_added', $userId, $userName, ['anexo' => $anexo->label]);

        return $anexo;
    }

    /**
     * @throws ContractException
     */
    public function removeAttachment(SignatureContractAttachment $attachment, ?int $userId, ?string $userName): void
    {
        $this->ensureAttachmentsOpen($attachment->contract);

        $this->event($attachment->contract, 'attachment_removed', $userId, $userName, ['anexo' => $attachment->label]);

        Storage::disk(config('signature.disk'))->delete($attachment->path);

        $attachment->delete();
    }

    /** Grava um evento no histórico do contrato. */
    public function event(SignatureContract $contract, string $event, ?int $userId, ?string $userName, array $payload = []): void
    {
        SignatureContractEvent::create([
            'signature_contract_id' => $contract->id,
            'event' => $event,
            'actor_id' => $userId,
            'actor_name' => $userName,
            'payload' => $payload ?: null,
        ]);
    }

    /**
     * As contratadas e fiadores com documento válido vão para o cadastro de
     * reaproveitamento — o próximo contrato com a mesma empresa sai
     * preenchido.
     */
    /**
     * O contratado informado no passo "Contratado" da criação (04/11/2026):
     * entra como a primeira contratada (mantendo o termo do modelo, como
     * LOCATÁRIO ou ANUNCIANTE) e preenche quem assina pela contratada — o
     * representante, na empresa; a própria pessoa, na pessoa física.
     *
     * @param  array<string, mixed>  $content
     * @param  array<string, mixed>  $party
     * @return array<string, mixed>
     */
    private function withMainParty(array $content, array $party): array
    {
        $partes = $content['parties'] ?? [];
        $indice = collect($partes)->search(fn(array $p) => $p['role'] === ContractContent::ROLE_CONTRATADA);
        $dados = array_intersect_key($party, array_flip([
            'kind', 'gender', 'name', 'document', 'address', 'details', 'rep_name', 'rep_cpf', 'email', 'phone',
        ]));

        if ($indice === false) {
            array_unshift($partes, ['role' => ContractContent::ROLE_CONTRATADA, 'term' => 'CONTRATADA'] + $dados);
        } else {
            $partes[$indice] = array_merge($partes[$indice], $dados);
        }

        $content['parties'] = $partes;
        $content = ContractContent::normalize($content);

        $parte = collect($content['parties'])->firstWhere('role', ContractContent::ROLE_CONTRATADA);
        $pj = $parte['kind'] === 'pj';
        $assinante = [
            'name' => $pj ? $parte['rep_name'] : $parte['name'],
            'cpf' => $pj ? $parte['rep_cpf'] : $parte['document'],
            'email' => $parte['email'],
            'phone' => $parte['phone'],
        ];

        $signers = $content['signers'] ?? [];
        $vago = collect($signers)->search(fn(array $s) => $s['party'] === ContractContent::SIGNER_CONTRATADA
            && $s['source'] === 'person' && $s['name'] === '');

        if ($vago !== false) {
            $signers[$vago] = array_merge($signers[$vago], $assinante);
        } elseif (!collect($signers)->contains('party', ContractContent::SIGNER_CONTRATADA)) {
            array_unshift($signers, ['party' => ContractContent::SIGNER_CONTRATADA, 'source' => 'person'] + $assinante);
        }

        $content['signers'] = $signers;

        return ContractContent::normalize($content);
    }

    private function rememberParties(array $content, ?string $userName): void
    {
        foreach ($content['parties'] ?? [] as $parte) {
            $tamanho = strlen($parte['document']);

            if ($parte['name'] === '' || ($parte['kind'] === 'pj' ? $tamanho !== 14 : $tamanho !== 11)) {
                continue;
            }

            // `active` não muda aqui: prestador inativado na tela de Prestadores continua inativo.
            $prestador = SignatureContractParty::firstOrNew(['document' => $parte['document']]);

            $prestador->fill([
                'kind' => $parte['kind'],
                'name' => $parte['name'],
                'data' => array_intersect_key($parte, array_flip(SignatureContractParty::DATA_FIELDS)),
                'updated_by_name' => $userName,
            ]);

            if (!$prestador->exists) {
                $prestador->created_by_name = $userName;
            }

            $prestador->save();
        }
    }

    /**
     * A versão que recebe a decisão: a que está com a Gerência ou, se a
     * Gerência mexeu no texto durante a validação, uma versão nova com o
     * ajuste (a anterior fica como substituída).
     */
    private function reviewerVersion(SignatureContract $contract, ?int $userId, ?string $userName): SignatureContractVersion
    {
        $atual = $contract->latestVersion();

        if ($atual && $atual->content == $contract->content) {
            return $atual;
        }

        $atual?->forceFill(['decision' => SignatureContractVersion::DECISION_SUPERSEDED])->save();

        $nova = $this->snapshot($contract, $userId, $userName, 'Ajustes feitos pela Gerência na validação.', true);

        $this->event($contract, 'reviewer_edited', $userId, $userName, ['versao' => $nova->version]);

        return $nova;
    }

    private function snapshot(SignatureContract $contract, ?int $userId, ?string $userName, ?string $note, bool $byReviewer): SignatureContractVersion
    {
        $numero = $contract->current_version + 1;

        $versao = SignatureContractVersion::create([
            'signature_contract_id' => $contract->id,
            'version' => $numero,
            'content' => $contract->content ?? [],
            'submitted_by' => $userId,
            'submitted_by_name' => $userName,
            'submitted_at' => now(),
            'note' => $note,
            'by_reviewer' => $byReviewer,
        ]);

        $contract->forceFill(['current_version' => $numero])->save();

        return $versao;
    }

    private function comment(SignatureContract $contract, SignatureContractVersion $version, ?string $clauseUid, string $body, ?int $userId, ?string $userName): void
    {
        SignatureContractComment::create([
            'signature_contract_id' => $contract->id,
            'signature_contract_version_id' => $version->id,
            'clause_uid' => $clauseUid,
            'body' => $body,
            'author_id' => $userId,
            'author_name' => $userName,
        ]);
    }

    /**
     * "CTL - 12/26": uma sequência para todos os setores, por ano, travada
     * na leitura para dois aprovando ao mesmo tempo não pegarem o mesmo
     * número. O aditivo repete o número do original com "A1", "A2"…
     */
    private function assignNumber(SignatureContract $contract): void
    {
        if ($contract->number) {
            return;
        }

        if ($contract->isAddendum()) {
            $original = SignatureContract::lockForUpdate()->findOrFail($contract->parent_id);
            $seq = (int) SignatureContract::where('parent_id', $original->id)->lockForUpdate()->max('addendum_seq') + 1;

            $contract->forceFill([
                'addendum_seq' => $seq,
                'number' => $original->number . ' A' . $seq,
            ])->save();

            // A ordem do aditivo ("1º TERMO ADITIVO…") fica a definitiva.
            $contract->forceFill(['content' => $this->withAddendumContext($contract, $contract->content ?? [])])->save();

            return;
        }

        $ano = (int) now()->format('Y');
        $seq = (int) SignatureContract::where('number_year', $ano)->lockForUpdate()->max('number_seq') + 1;

        $contract->forceFill([
            'number_year' => $ano,
            'number_seq' => $seq,
            'number' => config('signature.contracts.number_prefix') . ' - ' . $seq . '/' . now()->format('y'),
        ])->save();
    }

    /**
     * @throws ContractException
     */
    private function ensureReady(SignatureContract $contract, string $message): void
    {
        $erros = array_values(array_filter(
            $this->renderer->problems($contract->content ?? []),
            fn(array $p) => $p['level'] === 'error',
        ));

        if ($erros !== []) {
            throw new ContractException($message, $erros);
        }
    }

    /**
     * @throws ContractException
     */
    private function ensureInReview(SignatureContract $contract): void
    {
        if ($contract->status !== SignatureContract::STATUS_IN_REVIEW) {
            throw new ContractException('Este contrato não está em validação.');
        }
    }

    /**
     * @throws ContractException
     */
    private function ensureAttachmentsOpen(SignatureContract $contract): void
    {
        if (!in_array($contract->status, [SignatureContract::STATUS_DRAFT, SignatureContract::STATUS_RETURNED, SignatureContract::STATUS_IN_REVIEW], true)) {
            throw new ContractException('Os anexos só mudam enquanto o contrato não foi aprovado.');
        }
    }

    /**
     * E-mail fora da transação, e falha de envio não desfaz nada: o contrato
     * mudou de situação de qualquer jeito, e a tela mostra.
     *
     * @param  callable(): array<int, string>  $to  os destinatários (a consulta também fica dentro do try)
     */
    private function mail(callable $to, \Illuminate\Mail\Mailable $mailable): void
    {
        try {
            $destinatarios = array_values($to());

            if ($destinatarios === []) {
                return;
            }

            Mail::to($destinatarios)->send($mailable);
        } catch (\Throwable $e) {
            Log::warning('Contrato: e-mail de aviso não enviado.', ['erro' => $e->getMessage()]);
        }
    }
}
