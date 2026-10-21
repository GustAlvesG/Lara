<?php

namespace App\Http\Controllers\Signature;

use App\Exceptions\SignatureDocumentLockedException;
use App\Http\Controllers\Controller;
use App\Models\SignatureDocument;
use App\Models\SignatureRemoteLink;
use App\Models\SignatureSigner;
use App\Services\Signature\RemoteSignatureService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * A assinatura remota, do lado do ATENDENTE: enviar o link por e-mail e
 * revogá-lo. A mesma permissão de liberar o QR no tablet (`release`): é o
 * mesmo ato — entregar o documento a quem vai assinar —, por outro caminho.
 */
class RemoteLinkController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private RemoteSignatureService $remote)
    {
    }

    public function store(Request $request, SignatureDocument $signatureDocument, SignatureSigner $signatureSigner)
    {
        $this->authorize('release', $signatureDocument);

        abort_unless($signatureSigner->signature_document_id === $signatureDocument->id, 404);

        $dados = $request->validate([
            'email' => ['required', 'email', 'max:150'],
        ], [
            'email.required' => 'Informe o e-mail de quem vai assinar.',
            'email.email' => 'O e-mail informado não é válido.',
        ]);

        $voltar = route('signature-documents.show', $signatureDocument);

        try {
            $link = $this->remote->send(
                $signatureSigner,
                $dados['email'],
                auth()->id(),
                auth()->user()?->name,
                auth()->user()?->email,
            );
        } catch (SignatureDocumentLockedException $e) {
            return redirect($voltar)->with('warning', $e->getMessage());
        } catch (Throwable $e) {
            // SMTP fora do ar, endereço recusado: nada foi registrado como
            // enviado (a gravação é desfeita junto). O motivo vai ao log.
            Log::error('Falha ao enviar o link de assinatura remota.', [
                'document_id' => $signatureDocument->id,
                'signer_id' => $signatureSigner->id,
                'erro' => $e->getMessage(),
            ]);

            return redirect($voltar)->with('error', 'O e-mail não pôde ser enviado. Nada foi registrado como enviado.');
        }

        return redirect($voltar)->with(
            'success',
            'Link de assinatura enviado a ' . $link->maskedEmail() . '. Vale até ' . $link->expires_at->format('d/m/Y H:i') . '.',
        );
    }

    public function destroy(SignatureDocument $signatureDocument, SignatureRemoteLink $signatureRemoteLink)
    {
        $this->authorize('release', $signatureDocument);

        abort_unless($signatureRemoteLink->signer?->signature_document_id === $signatureDocument->id, 404);

        $voltar = route('signature-documents.show', $signatureDocument);

        try {
            $this->remote->revoke($signatureRemoteLink, auth()->id(), auth()->user()?->name);
        } catch (SignatureDocumentLockedException $e) {
            return redirect($voltar)->with('warning', $e->getMessage());
        }

        return redirect($voltar)->with('success', 'Link de assinatura revogado: ele não abre mais.');
    }
}
