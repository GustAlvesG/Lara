<?php

namespace App\Http\Controllers\Signature;

use App\Exceptions\SignatureDocumentLockedException;
use App\Http\Controllers\Controller;
use App\Models\SignatureAttachment;
use App\Models\SignatureDocument;
use App\Services\Signature\SignatureAttachmentService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Anexos do documento, na tela do atendente: enviar, ver e (em rascunho)
 * remover. As regras de estado são do SignatureAttachmentService.
 *
 * Recusa volta como "Atenção" (`warning`), não como erro: o que resolve é o
 * atendente mandar o arquivo certo, não procurar a TI.
 */
class AttachmentController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private SignatureAttachmentService $attachments)
    {
    }

    public function store(Request $request, SignatureDocument $signatureDocument)
    {
        $this->authorize('attach', $signatureDocument);

        $request->validate([
            'arquivo' => ['required', 'file', 'max:' . (int) config('signature.attachments.max_kb', 10240)],
            'item' => ['nullable', 'string', 'max:60'],
            'rotulo' => ['nullable', 'string', 'max:120'],
        ], [
            'arquivo.required' => 'Escolha o arquivo do anexo.',
            'arquivo.file' => 'O envio do arquivo falhou. Tente de novo.',
            'arquivo.max' => 'O arquivo é grande demais.',
        ]);

        $voltar = route('signature-documents.show', $signatureDocument) . '#anexos';

        try {
            $anexo = $this->attachments->store(
                $signatureDocument,
                $request->file('arquivo'),
                $request->filled('item') ? (string) $request->input('item') : null,
                $request->input('rotulo'),
                auth()->id(),
                auth()->user()?->name,
            );
        } catch (SignatureDocumentLockedException $e) {
            return redirect($voltar)->with('warning', $e->getMessage());
        }

        return redirect($voltar)->with('success', 'Anexo "' . $anexo->label . '" enviado.');
    }

    /** O arquivo, pelo disco privado — nunca por URL. */
    public function show(SignatureDocument $signatureDocument, SignatureAttachment $signatureAttachment)
    {
        $this->authorize('download', $signatureDocument);

        abort_unless($signatureAttachment->signature_document_id === $signatureDocument->id, 404);

        $disk = Storage::disk(config('signature.disk'));

        abort_unless($disk->exists($signatureAttachment->path), 404);

        return $disk->response(
            $signatureAttachment->path,
            'anexo-' . $signatureAttachment->id . '.' . $signatureAttachment->extension(),
            [
                'Content-Type' => $signatureAttachment->mime,
                // Documento pessoal: nunca em cache compartilhado.
                'Cache-Control' => 'private, max-age=0, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ],
            'inline',
        );
    }

    public function destroy(SignatureDocument $signatureDocument, SignatureAttachment $signatureAttachment)
    {
        $this->authorize('attach', $signatureDocument);

        abort_unless($signatureAttachment->signature_document_id === $signatureDocument->id, 404);

        $voltar = route('signature-documents.show', $signatureDocument) . '#anexos';

        try {
            $this->attachments->remove($signatureAttachment, auth()->id(), auth()->user()?->name);
        } catch (SignatureDocumentLockedException $e) {
            return redirect($voltar)->with('warning', $e->getMessage());
        }

        return redirect($voltar)->with('success', 'Anexo "' . $signatureAttachment->label . '" removido.');
    }
}
