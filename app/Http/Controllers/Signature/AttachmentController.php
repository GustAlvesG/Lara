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

    /** Arquivos num envio só. */
    public const MAX_FILES_PER_UPLOAD = 10;

    public function __construct(private SignatureAttachmentService $attachments)
    {
    }

    public function store(Request $request, SignatureDocument $signatureDocument)
    {
        $this->authorize('attach', $signatureDocument);

        /*
         | Vários arquivos de uma vez (`arquivos[]`, 26/10/2026), ou um só
         | (`arquivo`, o envio de antes). Cada arquivo é um anexo próprio, com o
         | seu hash e o seu evento na trilha — o mesmo que enviar um por um.
         */
        $maxKb = (int) config('signature.attachments.max_kb', 10240);

        $request->validate([
            'arquivo' => ['required_without:arquivos', 'nullable', 'file', 'max:' . $maxKb],
            'arquivos' => ['required_without:arquivo', 'nullable', 'array', 'max:' . self::MAX_FILES_PER_UPLOAD],
            'arquivos.*' => ['file', 'max:' . $maxKb],
            'item' => ['nullable', 'string', 'max:60'],
            'rotulo' => ['nullable', 'string', 'max:120'],
        ], [
            'arquivo.required_without' => 'Escolha o arquivo do anexo.',
            'arquivos.required_without' => 'Escolha o arquivo do anexo.',
            'arquivo.file' => 'O envio do arquivo falhou. Tente de novo.',
            'arquivos.*.file' => 'O envio de um dos arquivos falhou. Tente de novo.',
            'arquivo.max' => 'O arquivo é grande demais.',
            'arquivos.*.max' => 'Um dos arquivos é grande demais.',
            'arquivos.max' => 'Envie no máximo ' . self::MAX_FILES_PER_UPLOAD . ' arquivos de uma vez.',
        ]);

        $voltar = route('signature-documents.show', $signatureDocument) . '#anexos';
        $arquivos = $request->file('arquivos') ?: [$request->file('arquivo')];

        $enviados = [];
        $recusas = [];

        foreach ($arquivos as $arquivo) {
            try {
                $enviados[] = $this->attachments->store(
                    $signatureDocument->fresh(),
                    $arquivo,
                    $request->filled('item') ? (string) $request->input('item') : null,
                    $request->input('rotulo'),
                    auth()->id(),
                    auth()->user()?->name,
                );
            } catch (SignatureDocumentLockedException $e) {
                // Um arquivo recusado (tipo, estado) não segura os outros.
                $recusas[] = (count($arquivos) > 1 ? $arquivo->getClientOriginalName() . ': ' : '') . $e->getMessage();
            }
        }

        $resposta = redirect($voltar);

        if ($recusas !== []) {
            $resposta->with('warning', implode(' ', $recusas));
        }

        if ($enviados !== []) {
            $resposta->with('success', count($enviados) === 1
                ? 'Anexo "' . $enviados[0]->label . '" enviado.'
                : count($enviados) . ' arquivos enviados em "' . $enviados[0]->label . '".');
        }

        return $resposta;
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
