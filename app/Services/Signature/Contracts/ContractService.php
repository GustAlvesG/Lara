<?php

namespace App\Services\Signature\Contracts;

use App\Exceptions\ContractException;
use App\Exceptions\SignatureDocumentLockedException;
use App\Mail\SignatureContractDecisionMail;
use App\Mail\SignatureContractReviewMail;
use App\Models\SignatureContract;
use App\Models\SignatureContractAttachment;
use App\Models\SignatureContractComment;
use App\Models\SignatureContractEvent;
use App\Models\SignatureContractModel;
use App\Models\SignatureContractParty;
use App\Models\SignatureContractVersion;
use App\Models\SignatureDocument;
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
    ) {
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
    ): SignatureContract {
        $conteudo = $model ? $this->modelContent($model) : ContractContent::blank();

        if (filled($title)) {
            $conteudo['title'] = mb_substr(trim((string) $title), 0, 200);
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
     * @param  array<string, mixed>  $input  o que veio do formulário
     *
     * @throws ContractException
     */
    public function save(SignatureContract $contract, array $input, ?int $userId, ?string $userName): SignatureContract
    {
        if (!$contract->isEditableBySector() && $contract->status !== SignatureContract::STATUS_IN_REVIEW) {
            throw new ContractException('Contrato ' . mb_strtolower($contract->statusLabel()) . ': o texto não muda mais.'
                . ($contract->status === SignatureContract::STATUS_APPROVED ? ' Para alterar, reabra o contrato.' : ''));
        }

        $conteudo = ContractContent::normalize($input);

        $contract->forceFill([
            'content' => $conteudo,
            'title' => $conteudo['title'] ?: $contract->title,
        ])->save();

        $this->rememberParties($conteudo, $userName);

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
    public function approve(SignatureContract $contract, ?string $note, ?int $userId, ?string $userName): array
    {
        $this->ensureInReview($contract);
        $this->ensureReady($contract, 'O contrato ainda tem o que corrigir. Ajuste ou devolva antes de aprovar.');

        $versao = DB::transaction(function () use ($contract, $note, $userId, $userName) {
            $versao = $this->reviewerVersion($contract, $userId, $userName);

            $versao->forceFill([
                'decision' => SignatureContractVersion::DECISION_APPROVED,
                'decided_by' => $userId,
                'decided_by_name' => $userName,
                'decided_at' => now(),
                'decision_note' => trim((string) $note) ?: null,
            ])->save();

            $this->assignNumber($contract);

            $contract->forceFill([
                'status' => SignatureContract::STATUS_APPROVED,
                'approved_version_id' => $versao->id,
                'approved_at' => now(),
                'approved_by' => $userId,
                'approved_by_name' => $userName,
            ])->save();

            $this->event($contract, 'approved', $userId, $userName, ['versao' => $versao->version, 'numero' => $contract->number]);

            return $versao;
        });

        $resultado = $this->generateDocument($contract->fresh(), $userId, $userName);

        $this->mail(fn() => array_filter([$this->recipients->author($contract)]), new SignatureContractDecisionMail($contract->fresh(), $versao));

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

        if ($atual && $atual->status !== SignatureDocument::STATUS_CANCELED) {
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

        if ($documento && $documento->isFrozen() && $documento->status !== SignatureDocument::STATUS_CANCELED) {
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

    /**
     * @throws ContractException
     */
    public function cancel(SignatureContract $contract, ?string $reason, ?int $userId, ?string $userName): SignatureContract
    {
        if (in_array($contract->status, [SignatureContract::STATUS_APPROVED, SignatureContract::STATUS_CANCELED], true)) {
            throw new ContractException($contract->status === SignatureContract::STATUS_APPROVED
                ? 'Contrato aprovado não se cancela aqui: reabra-o, ou cancele o documento de assinatura.'
                : 'O contrato já está cancelado.');
        }

        $contract->forceFill(['status' => SignatureContract::STATUS_CANCELED, 'canceled_at' => now()])->save();

        $this->event($contract, 'canceled', $userId, $userName, array_filter(['motivo' => trim((string) $reason) ?: null]));

        return $contract;
    }

    /**
     * Aditivo de um contrato aprovado: herda partes, variáveis, signatários e
     * o estabelecimento; começa com uma cláusula em branco. O número
     * ("CTL - 12/26 A1") sai na aprovação do aditivo.
     *
     * @throws ContractException
     */
    public function createAddendum(SignatureContract $contract, ?int $userId, ?string $userName): SignatureContract
    {
        if ($contract->status !== SignatureContract::STATUS_APPROVED) {
            throw new ContractException('Aditivo só de contrato aprovado.');
        }

        // Aditivo de aditivo é aditivo do contrato original.
        $original = $contract->isAddendum() && $contract->parent ? $contract->parent : $contract;

        $base = $original->content ?? [];

        $conteudo = ContractContent::normalize(array_merge($base, [
            'title' => 'Termo aditivo ao contrato ' . $original->number,
            'considerations' => [],
            'clauses' => [
                ['title' => 'Do objeto do aditivo', 'items' => [['level' => 1, 'html' => '']]],
            ],
        ]));

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

            $this->event($aditivo, 'created', $userId, $userName, ['aditivo_de' => $original->number]);
            $this->event($original, 'addendum_created', $userId, $userName, ['aditivo' => $aditivo->id]);

            return $aditivo;
        });
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

        $modelo = ContractContent::normalize([
            'title' => $conteudo['title'] ?? $contract->title,
            'establishment' => $conteudo['establishment'] ?? null,
            'contractor_term' => $conteudo['contractor_term'] ?? null,
            'parties' => array_map(fn(array $p) => [
                'role' => $p['role'],
                'term' => $p['term'],
                'kind' => $p['kind'],
            ], $conteudo['parties'] ?? []),
            'variables' => array_map(fn(array $v) => ['key' => $v['key'], 'label' => $v['label'], 'type' => $v['type'], 'value' => ''], $conteudo['variables'] ?? []),
            'considerations' => $conteudo['considerations'] ?? [],
            'clauses' => $conteudo['clauses'] ?? [],
            'signers' => [],
        ]);

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
    private function rememberParties(array $content, ?string $userName): void
    {
        foreach ($content['parties'] ?? [] as $parte) {
            $tamanho = strlen($parte['document']);

            if ($parte['name'] === '' || ($parte['kind'] === 'pj' ? $tamanho !== 14 : $tamanho !== 11)) {
                continue;
            }

            SignatureContractParty::updateOrCreate(
                ['document' => $parte['document']],
                [
                    'kind' => $parte['kind'],
                    'name' => $parte['name'],
                    'data' => array_intersect_key($parte, array_flip([
                        'gender', 'address', 'details', 'rep_name', 'rep_cpf', 'email', 'phone',
                    ])),
                    'updated_by_name' => $userName,
                ],
            );
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
