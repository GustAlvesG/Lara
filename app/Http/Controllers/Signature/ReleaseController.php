<?php

namespace App\Http\Controllers\Signature;

use App\Exceptions\SignatureDocumentLockedException;
use App\Exceptions\SignatureSessionException;
use App\Http\Controllers\Controller;
use App\Models\SignatureDocument;
use App\Models\SignatureRequest;
use App\Models\SignatureSigner;
use App\Services\Signature\SignatureRequestService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

/**
 * A liberação por QR Code, do lado do ATENDENTE.
 *
 * Um QR por signatário, um por vez, gerado na hora e válido por minutos. O
 * token em claro existe só na resposta desta rota — é dela que a tela desenha
 * o QR, no próprio navegador, e ele não é gravado em lugar nenhum.
 *
 * Responde JSON porque a tela do atendente acompanha o atendimento sem
 * recarregar: o QR aparece, a contagem regressiva corre, e o estado muda
 * sozinho conforme o tablet anda.
 */
class ReleaseController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private SignatureRequestService $requests)
    {
    }

    /**
     * Gera (ou regera) o QR de um signatário.
     *
     * Regerar invalida o anterior — é a mesma rota de propósito: "gerar outro"
     * e "gerar o primeiro" são o mesmo ato para quem está no balcão, e ter
     * duas rotas abriria a chance de dois QRs válidos ao mesmo tempo.
     */
    public function store(SignatureDocument $signatureDocument, SignatureSigner $signatureSigner)
    {
        $this->authorize('release', $signatureDocument);

        abort_if($signatureSigner->signature_document_id !== $signatureDocument->id, 404);

        try {
            $liberacao = $this->requests->issue($signatureSigner, auth()->id());
        } catch (SignatureDocumentLockedException $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        }

        return response()->json([
            'request_id' => $liberacao['request']->id,
            // O conteúdo do QR. Vai para a tela, vira imagem, e acaba aí.
            'qr_payload' => $liberacao['payload'],
            'expires_in' => $liberacao['request']->secondsToExpire(),
            'signer' => [
                'id' => $signatureSigner->id,
                'name' => $signatureSigner->name,
                'role' => $signatureSigner->roleLabel(),
            ],
        ], 201);
    }

    /** Cancela a liberação: o QR não lido para de valer, e a sessão aberta cai. */
    public function destroy(SignatureDocument $signatureDocument, SignatureRequest $signatureRequest)
    {
        $this->authorize('release', $signatureDocument);

        abort_if(
            $signatureRequest->signer?->signature_document_id !== $signatureDocument->id,
            404,
        );

        try {
            $this->requests->cancel($signatureRequest, auth()->id());
        } catch (SignatureSessionException $e) {
            return response()->json(['error' => $e->getMessage()], $e->status);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * O estado do atendimento, para a tela do atendente acompanhar.
     *
     * É polling, e não broadcasting: este projeto tem `BROADCAST_CONNECTION=log`
     * — não há canal em tempo real para usar, e montar um só para esta tela
     * seria mais infraestrutura do que o problema pede.
     */
    public function status(Request $request, SignatureDocument $signatureDocument)
    {
        $this->authorize('view', $signatureDocument);

        $signatureDocument->load(['signers.requests']);

        return response()->json([
            'status' => $signatureDocument->status,
            'status_label' => $signatureDocument->statusLabel(),
            'finalized' => $signatureDocument->status === SignatureDocument::STATUS_FINALIZED,
            'signers' => $signatureDocument->signers->map(function (SignatureSigner $signer) {
                $ultima = $signer->requests->first();

                return [
                    'id' => $signer->id,
                    'name' => $signer->name,
                    'role' => $signer->roleLabel(),
                    'status' => $signer->status,
                    'status_label' => $signer->statusLabel(),
                    'signed_at' => $signer->signed_at?->format('d/m/Y H:i:s'),
                    'request' => $ultima ? [
                        'id' => $ultima->id,
                        'status' => $ultima->status,
                        'status_label' => $ultima->statusLabel(),
                        'expires_in' => $ultima->secondsToExpire(),
                        'session_remaining' => $ultima->secondsToSessionEnd(),
                    ] : null,
                ];
            })->all(),
            'server_time' => now()->toIso8601String(),
        ]);
    }
}
