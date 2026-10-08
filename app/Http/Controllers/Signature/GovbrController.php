<?php

namespace App\Http\Controllers\Signature;

use App\Exceptions\SignatureDocumentLockedException;
use App\Http\Controllers\Controller;
use App\Models\SignatureDocument;
use App\Models\SignatureGovbrCheck;
use App\Models\SignatureSigner;
use App\Services\Signature\Govbr\GovbrCheckService;
use App\Services\Signature\Govbr\GovbrInviteService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Aba "Assinatura gov.br" da tela do documento: o atendente prepara o
 * documento para o gov.br, recebe de volta o PDF assinado, envia aqui, e vê na
 * mesma aba a conferência — e quem ela deu como assinado.
 *
 * Recusas e conferências que não concluem voltam como "Atenção", e não como
 * erro: o aviso de erro do sistema manda procurar a TI, e aqui o que resolve é
 * pedir à pessoa o arquivo certo.
 */
class GovbrController extends Controller
{
    use AuthorizesRequests;

    public function prepare(SignatureDocument $signatureDocument, GovbrCheckService $service)
    {
        $this->authorize('checkGovbr', $signatureDocument);

        $voltar = route('signature-documents.show', [$signatureDocument, 'aba' => 'govbr']);

        try {
            $documento = $service->prepare($signatureDocument, auth()->id(), auth()->user()?->name);
        } catch (SignatureDocumentLockedException $e) {
            return redirect($voltar)->with('warning', $e->getMessage());
        }

        return redirect($voltar)->with(
            'success',
            'Documento preparado para o gov.br, com prazo até ' . $documento->expires_at?->format('d/m/Y H:i')
            . '. Baixe o PDF original agora e envie à pessoa.',
        );
    }

    /**
     * Botão "Enviar por e-mail": o PDF a assinar, para o próximo signatário. O
     * e-mail digitado vira o do signatário; a resposta vai para o atendente.
     */
    public function invite(
        Request $request,
        SignatureDocument $signatureDocument,
        SignatureSigner $signatureSigner,
        GovbrInviteService $invites,
    ) {
        $this->authorize('checkGovbr', $signatureDocument);

        abort_unless($signatureSigner->signature_document_id === $signatureDocument->id, 404);

        $dados = $request->validate([
            'email' => ['required', 'email', 'max:191'],
        ], [
            'email.required' => 'Informe o e-mail de quem vai assinar.',
            'email.email' => 'O e-mail informado não é válido.',
        ]);

        $voltar = route('signature-documents.show', [$signatureDocument, 'aba' => 'govbr']);

        try {
            $convite = $invites->send(
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
            Log::error('Falha ao enviar o convite de assinatura gov.br.', [
                'document_id' => $signatureDocument->id,
                'signer_id' => $signatureSigner->id,
                'erro' => $e->getMessage(),
            ]);

            return redirect($voltar)->with('error', 'O e-mail não pôde ser enviado. Nada foi registrado como enviado.');
        }

        return redirect($voltar)->with(
            'success',
            'PDF enviado a ' . $convite->maskedEmail() . '. Quando o arquivo assinado voltar, envie-o aqui para conferir.',
        );
    }

    public function store(Request $request, SignatureDocument $signatureDocument, GovbrCheckService $service)
    {
        $this->authorize('checkGovbr', $signatureDocument);

        $request->validate([
            'documento' => [
                'required',
                'file',
                'mimetypes:application/pdf',
                'max:' . (int) config('signature.govbr.max_upload_kb', 20480),
            ],
        ], [
            'documento.required' => 'Escolha o PDF assinado pelo gov.br.',
            'documento.mimetypes' => 'Envie o arquivo PDF que a pessoa baixou do gov.br depois de assinar.',
            'documento.max' => 'O arquivo é grande demais.',
        ]);

        $voltar = route('signature-documents.show', [$signatureDocument, 'aba' => 'govbr']);

        try {
            $conferencia = $service->check(
                $signatureDocument,
                (string) file_get_contents($request->file('documento')->getRealPath()),
                auth()->id(),
                auth()->user()?->name,
            );
        } catch (SignatureDocumentLockedException $e) {
            return redirect($voltar)->with('warning', $e->getMessage());
        }

        if (!$conferencia->valid) {
            return redirect($voltar)->with('warning', 'O arquivo foi recusado. Veja abaixo o que não passou.');
        }

        if ($motivo = $conferencia->conclusionReason()) {
            return redirect($voltar)->with('warning', 'O arquivo é válido, mas não concluiu a assinatura: ' . $motivo);
        }

        $quem = implode(', ', $conferencia->concludedNames());

        if (!$conferencia->closedDocument()) {
            return redirect($voltar)->with('success', "Assinatura de {$quem} registrada. Envie este mesmo arquivo ao próximo signatário.");
        }

        // Todos assinaram, mas a conclusão espera os anexos obrigatórios.
        $faltando = $signatureDocument->fresh()->missingAttachments();

        return redirect($voltar)->with('success', $faltando === []
            ? "Assinatura de {$quem} registrada. Todos assinaram: o documento está assinado e o PDF final é este arquivo."
            : "Assinatura de {$quem} registrada. Todos assinaram; para concluir, falta enviar os anexos: "
                . implode(', ', $faltando) . '.');
    }

    /** O arquivo enviado, como chegou — pelo disco privado, nunca por URL. */
    public function pdf(SignatureDocument $signatureDocument, SignatureGovbrCheck $signatureGovbrCheck)
    {
        $this->authorize('download', $signatureDocument);

        abort_unless($signatureGovbrCheck->signature_document_id === $signatureDocument->id, 404);

        $disk = Storage::disk(config('signature.disk'));

        abort_unless($disk->exists($signatureGovbrCheck->file_path), 404);

        return $disk->response($signatureGovbrCheck->file_path, null, [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, max-age=0, no-store',
        ]);
    }
}
