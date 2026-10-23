<?php

namespace App\Services\Signature;

use App\Exceptions\SignatureDocumentLockedException;
use App\Jobs\FinalizeSignatureDocument;
use App\Models\SignatureAttachment;
use App\Models\SignatureAuditEvent;
use App\Models\SignatureDocument;
use App\Models\SignatureRequest;
use App\Models\SignatureSigner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Anexos do documento: identidade, comprovante — o que o modelo ou o próprio
 * documento pedir.
 *
 * O que se pede funciona como as perguntas: uma lista de itens com rótulo e
 * obrigatoriedade, no modelo (`attachments`) e no documento
 * (`attachment_requirements`).
 *
 * O obrigatório NÃO trava o congelamento nem a assinatura: trava a
 * CONCLUSÃO. Com todos assinados e anexo obrigatório faltando, o documento
 * fica "Assinado", à espera; o envio que completa a lista despacha a
 * finalização (FinalizeSignatureDocument, que confere de novo).
 *
 * Quem envia é o atendente, na tela do documento:
 *
 *  - em rascunho, envia e remove à vontade;
 *  - congelado (aguardando assinatura) ou já assinado, ainda envia — mas não
 *    remove: o que entrou fica, com o hash na trilha;
 *  - finalizado, cancelado, expirado, recusado: nada muda. O manifesto
 *    imprime o hash de cada anexo, e um anexo novo depois dele ficaria de fora.
 *
 * Na assinatura remota, o próprio signatário também envia, pela tela do link
 * (`$via` = a liberação remota): só os itens pedidos, nunca avulso. Ele pode
 * apagar o que ELE enviou enquanto não assinou (`removeOwn`) — o que o
 * atendente enviou não é dele para apagar. E no remoto o obrigatório trava a
 * ASSINATURA, não só a conclusão (ver RemoteSignatureService::sign).
 */
class SignatureAttachmentService
{
    public function __construct(private SignatureStateMachine $states)
    {
    }

    /**
     * Os itens pedidos, na forma gravada: chave estável, rótulo, obrigatório.
     *
     * A chave sai do rótulo (`doc_comprovante_de_residencia`), com o prefixo
     * de quem pediu — o modelo (`mod_`) ou o documento (`doc_`) —, para as
     * duas listas nunca colidirem.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array{key: string, label: string, required: bool}>
     */
    public static function normalize(array $rows, string $prefix): array
    {
        $itens = [];

        foreach ($rows as $row) {
            $rotulo = trim((string) ($row['label'] ?? ''));

            if ($rotulo === '') {
                continue;
            }

            $base = $prefix . '_' . (Str::slug(mb_substr($rotulo, 0, 50), '_') ?: 'anexo');
            $chave = $base;

            for ($n = 2; isset($itens[$chave]); $n++) {
                $chave = $base . '_' . $n;
            }

            $itens[$chave] = [
                'key' => $chave,
                'label' => mb_substr($rotulo, 0, 120),
                'required' => filter_var($row['required'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ];
        }

        return array_values($itens);
    }

    /** Por que não dá para enviar anexo agora. null = dá. */
    public function uploadBlockReason(SignatureDocument $document): ?string
    {
        return in_array($document->status, [
            SignatureDocument::STATUS_DRAFT,
            SignatureDocument::STATUS_AWAITING_SIGNATURE,
            SignatureDocument::STATUS_SIGNED,
        ], true)
            ? null
            : 'Documento ' . mb_strtolower($document->statusLabel()) . ': os anexos não mudam mais.';
    }

    /** Por que este anexo não pode ser removido. null = pode. */
    public function removeBlockReason(SignatureAttachment $attachment): ?string
    {
        return $attachment->document->status === SignatureDocument::STATUS_DRAFT
            ? null
            : 'Depois do congelamento, anexo enviado não é removido: ele fica registrado na trilha.';
    }

    /**
     * Guarda o arquivo e registra.
     *
     * @param  ?string  $key    o item pedido; null = anexo avulso, com `$label`
     * @param  ?string  $label  o nome do anexo avulso
     * @param  ?SignatureRequest  $via  a liberação remota, quando quem envia é o signatário
     * @param  array{ip?: ?string, user_agent?: ?string}  $context  de quem assina, no envio remoto
     *
     * @throws SignatureDocumentLockedException
     */
    public function store(
        SignatureDocument $document,
        UploadedFile $file,
        ?string $key,
        ?string $label = null,
        ?int $userId = null,
        ?string $userName = null,
        ?SignatureRequest $via = null,
        array $context = [],
    ): SignatureAttachment {
        if ($motivo = $this->uploadBlockReason($document)) {
            throw new SignatureDocumentLockedException($motivo);
        }

        $signatario = $via?->signer;

        if ($signatario) {
            // O signatário envia só o que foi pedido, e só antes de assinar.
            if ($key === null) {
                throw new SignatureDocumentLockedException('Escolha qual dos anexos pedidos você está enviando.');
            }

            if ($signatario->status !== SignatureSigner::STATUS_PENDING) {
                throw new SignatureDocumentLockedException('Depois de assinar, os anexos são enviados pelo clube.');
            }

            $userId = null;
            $userName = $signatario->name . ' (signatário, pelo link)';
        }

        if ($key !== null) {
            $item = collect($document->attachmentRequirements())->firstWhere('key', $key);

            if (!$item) {
                throw new SignatureDocumentLockedException('Este anexo não é pedido por este documento.');
            }

            $label = $item['label'];
        } elseif (trim((string) $label) === '') {
            throw new SignatureDocumentLockedException('Diga o que é o anexo.');
        }

        $bytes = (string) file_get_contents($file->getRealPath());

        // O tipo vem do CONTEÚDO, e não da extensão nem do que o navegador diz.
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: '';

        if (!isset(SignatureAttachment::MIME_EXTENSIONS[$mime])) {
            throw new SignatureDocumentLockedException('Envie PDF, JPG ou PNG.');
        }

        $hash = hash('sha256', $bytes);
        $caminho = config('signature.paths.documents') . '/' . $document->id . '/anexos/'
            . now()->format('Ymd-His') . '-' . substr($hash, 0, 12) . '.' . SignatureAttachment::MIME_EXTENSIONS[$mime];

        Storage::disk(config('signature.disk'))->put($caminho, $bytes);

        $anexo = DB::transaction(function () use ($document, $file, $key, $label, $caminho, $mime, $bytes, $hash, $userId, $userName, $signatario, $via, $context) {
            $anexo = SignatureAttachment::create([
                'signature_document_id' => $document->id,
                'requirement_key' => $key,
                'label' => mb_substr(trim((string) $label), 0, 120),
                'original_name' => mb_substr($file->getClientOriginalName() ?: 'arquivo', 0, 191),
                'path' => $caminho,
                'mime' => $mime,
                'bytes' => strlen($bytes),
                'sha256' => $hash,
                'uploaded_by' => $userId,
                'uploaded_by_name' => $userName,
                'uploaded_by_signer_id' => $signatario?->id,
            ]);

            // Sem o nome do arquivo: costuma trazer o nome da pessoa.
            $this->states->note($document, SignatureAuditEvent::EVENT_ATTACHMENT_ADDED, array_merge($this->origin($userId, $via, $context), [
                'payload' => [
                    'anexo' => $anexo->id,
                    'item' => $anexo->label,
                    'tipo' => $mime,
                    'bytes' => $anexo->bytes,
                    'sha256' => $hash,
                    'enviado_por' => $userName,
                ],
            ]));

            return $anexo;
        });

        // Todos já assinaram e este era o anexo que faltava: agora conclui.
        $document = $document->fresh();

        if ($document->status === SignatureDocument::STATUS_SIGNED && $document->missingAttachments() === []) {
            FinalizeSignatureDocument::dispatch($document->id);
        }

        return $anexo;
    }

    /**
     * Remove um anexo do rascunho — o arquivo e o registro. A trilha guarda
     * que ele existiu.
     *
     * @throws SignatureDocumentLockedException
     */
    public function remove(SignatureAttachment $attachment, ?int $userId = null, ?string $userName = null): void
    {
        if ($motivo = $this->removeBlockReason($attachment)) {
            throw new SignatureDocumentLockedException($motivo);
        }

        DB::transaction(function () use ($attachment, $userId, $userName) {
            $this->states->note($attachment->document, SignatureAuditEvent::EVENT_ATTACHMENT_REMOVED, [
                'actor_id' => $userId,
                'payload' => [
                    'anexo' => $attachment->id,
                    'item' => $attachment->label,
                    'sha256' => $attachment->sha256,
                    'removido_por' => $userName,
                ],
            ]);

            $attachment->delete();
        });

        Storage::disk(config('signature.disk'))->delete($attachment->path);
    }

    /**
     * Por que o signatário não pode apagar este anexo. null = pode: é dele
     * (enviado pela tela do link), ele ainda não assinou e o documento ainda
     * aceita anexo.
     */
    public function ownRemoveBlockReason(SignatureAttachment $attachment, SignatureRequest $via): ?string
    {
        $signatario = $via->signer;

        if ($attachment->signature_document_id !== $signatario->signature_document_id
            || $attachment->uploaded_by_signer_id !== $signatario->id) {
            return 'Só é possível apagar o anexo que você mesmo enviou.';
        }

        if ($signatario->status !== SignatureSigner::STATUS_PENDING) {
            return 'Depois de assinar, os anexos não mudam mais por aqui.';
        }

        return $this->uploadBlockReason($attachment->document);
    }

    /**
     * O signatário apaga o anexo que ele mesmo enviou, antes de assinar — é
     * assim que ele troca um arquivo errado. A trilha guarda que existiu.
     *
     * @param  array{ip?: ?string, user_agent?: ?string}  $context
     *
     * @throws SignatureDocumentLockedException
     */
    public function removeOwn(SignatureAttachment $attachment, SignatureRequest $via, array $context = []): void
    {
        if ($motivo = $this->ownRemoveBlockReason($attachment, $via)) {
            throw new SignatureDocumentLockedException($motivo);
        }

        DB::transaction(function () use ($attachment, $via, $context) {
            $this->states->note($attachment->document, SignatureAuditEvent::EVENT_ATTACHMENT_REMOVED, array_merge($this->origin(null, $via, $context), [
                'payload' => [
                    'anexo' => $attachment->id,
                    'item' => $attachment->label,
                    'sha256' => $attachment->sha256,
                    'removido_por' => $via->signer->name . ' (signatário, pelo link)',
                ],
            ]));

            $attachment->delete();
        });

        Storage::disk(config('signature.disk'))->delete($attachment->path);
    }

    /**
     * Quem fez, para a trilha: o atendente, ou o signatário pela tela do link
     * (com o IP e o navegador dele).
     *
     * @param  array{ip?: ?string, user_agent?: ?string}  $context
     * @return array<string, mixed>
     */
    private function origin(?int $userId, ?SignatureRequest $via, array $context): array
    {
        if (!$via) {
            return ['actor_id' => $userId];
        }

        return [
            'signer' => $via->signature_signer_id,
            'request' => $via->id,
            'actor_type' => SignatureAuditEvent::ACTOR_KIOSK,
            'ip' => $context['ip'] ?? null,
            'user_agent' => $context['user_agent'] ?? null,
        ];
    }
}
