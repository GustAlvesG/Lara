<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\SignatureDocumentLockedException;
use App\Exceptions\SignatureFormException;
use App\Exceptions\SignatureRemoteLinkException;
use App\Exceptions\SignatureSessionException;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Signature\Concerns\RespondsWithKioskSession;
use App\Http\Middleware\EnsureSignatureKioskSession;
use App\Http\Middleware\EnsureSignatureRemoteClient;
use App\Models\SignatureAttachment;
use App\Models\SignatureAuditEvent;
use App\Models\SignatureRemoteLink;
use App\Models\SignatureRequest;
use App\Services\Signature\RemoteSignatureService;
use App\Services\Signature\SignatureAttachmentService;
use App\Services\Signature\SignatureCaptureService;
use App\Services\Signature\SignatureRequestService;
use App\Services\Signature\SignatureSigningDataService;
use App\Services\Signature\SignatureStateMachine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * A API da assinatura remota, consumida pelo SERVIDOR do site externo
 * (Next.js, na DMZ). É o tablet do balcão em forma de API: as mesmas etapas,
 * os mesmos serviços, as mesmas travas — ver RemoteSignatureService para o
 * que muda (CPF completo, foto obrigatória, tentativas no link).
 *
 * Duas portas sem sessão — `link` (a página de entrada) e `abrir` (começa) —,
 * ambas pelo token do e-mail no CORPO, nunca na URL: um token na URL acaba em
 * log de acesso. O resto pede a sessão no header `X-Signature-Session`.
 *
 * Toda recusa responde `{error, reason}`: `error` é a mensagem pronta para a
 * tela; `reason` é estável, para o site escolher o que mostrar.
 *
 * O IP e o navegador gravados na trilha são os de QUEM ASSINA, repassados pelo
 * site (EnsureSignatureRemoteClient) — nunca `$request->ip()`, que aqui é o do
 * servidor do site.
 */
class RemoteSignatureApiController extends Controller
{
    use RespondsWithKioskSession;

    public function __construct(
        private RemoteSignatureService $remote,
        private SignatureRequestService $requests,
        private SignatureStateMachine $states,
        private SignatureSigningDataService $signingData,
    ) {
    }

    /** A página de entrada: o que é, para quem, até quando. Não abre sessão. */
    public function link(Request $request): JsonResponse
    {
        $dados = $request->validate(['token' => ['required', 'string', 'max:100']]);

        try {
            $link = $this->remote->resolve($dados['token']);
        } catch (SignatureRemoteLinkException $e) {
            return $this->fail($e);
        }

        return response()->json($this->remote->summary($link));
    }

    /**
     * Começa: abre a sessão e devolve o segredo dela, que existe só nesta
     * resposta. O site o guarda (cookie httpOnly do domínio dele) e o repassa
     * em `X-Signature-Session` dali em diante.
     */
    public function open(Request $request): JsonResponse
    {
        $dados = $request->validate(['token' => ['required', 'string', 'max:100']]);

        try {
            $sessao = $this->remote->open($dados['token'], $this->context($request));
        } catch (SignatureSessionException $e) {
            return $this->fail($e);
        }

        return response()->json(array_merge(
            ['session_token' => $sessao['session_token']],
            $this->payload($sessao['request']->fresh()),
        ));
    }

    /** Estado da sessão — o site consulta para a contagem e para cair se o clube cancelar. */
    public function session(Request $request): JsonResponse
    {
        return response()->json($this->payload($this->current($request)));
    }

    /**
     * O PDF original, o mesmo arquivo cujo hash a trilha cita. O site o
     * repassa ao navegador (PDF.js), sem guardar.
     */
    public function pdf(Request $request)
    {
        if ($recusa = $this->identityRequired($request)) {
            return $recusa;
        }

        $documento = $this->current($request)->signer->document;

        abort_if(!$documento->original_path, 404);

        $disk = Storage::disk(config('signature.disk'));

        abort_if(!$disk->exists($documento->original_path), 404);

        return $disk->response($documento->original_path, 'documento-' . $documento->validation_code . '.pdf', [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, max-age=0, no-store',
        ]);
    }

    /** Respostas às perguntas do documento — antes da leitura, como no tablet. */
    public function answers(Request $request): JsonResponse
    {
        if ($recusa = $this->identityRequired($request)) {
            return $recusa;
        }

        $solicitacao = $this->current($request);

        $dados = $request->validate([
            'answers' => ['present', 'array', 'max:60'],
        ]);

        try {
            $this->signingData->answer(
                $solicitacao->signer->document,
                $dados['answers'],
                $this->auditContext($request, $solicitacao),
            );
        } catch (SignatureFormException $e) {
            return response()->json(['error' => $e->getMessage(), 'reason' => 'invalid_answers', 'errors' => $e->errors], 422);
        } catch (SignatureSessionException $e) {
            return $this->fail($e);
        }

        return response()->json($this->payload($solicitacao->fresh()));
    }

    /** Registra que a pessoa leu até o fim — o que o navegador informou, com a hora do servidor. */
    public function viewed(Request $request): JsonResponse
    {
        if ($recusa = $this->identityRequired($request)) {
            return $recusa;
        }

        $solicitacao = $this->current($request);

        $dados = $request->validate([
            'read_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'scrolled_to_end' => ['nullable', 'boolean'],
        ]);

        $this->states->note($solicitacao->signer->signature_document_id, SignatureAuditEvent::EVENT_VIEWED, array_merge(
            $this->auditContext($request, $solicitacao),
            ['payload' => [
                'segundos_de_leitura' => $dados['read_seconds'] ?? null,
                'rolou_ate_o_fim' => (bool) ($dados['scrolled_to_end'] ?? false),
            ]],
        ));

        return response()->json(['ok' => true]);
    }

    /** O CPF completo. As tentativas contam no link. */
    public function identity(Request $request): JsonResponse
    {
        $solicitacao = $this->current($request);

        $dados = $request->validate([
            'cpf' => ['required', 'string', 'max:20'],
        ]);

        try {
            $this->remote->confirmIdentity($solicitacao, $dados['cpf'], $this->context($request));
        } catch (SignatureSessionException $e) {
            $link = $solicitacao->remoteLink()->first();

            // A quinta errada bloqueou o link: a tela é a de "peça outro link".
            if ($link?->status === SignatureRemoteLink::STATUS_LOCKED) {
                return $this->fail(SignatureRemoteLinkException::locked(), ['attempts_left' => 0]);
            }

            return $this->fail(
                $e->status === 422 ? new SignatureRemoteLinkException($e->getMessage(), 422, 'identity_failed') : $e,
                ['attempts_left' => max(0, SignatureCaptureService::MAX_IDENTITY_ATTEMPTS - (int) $link?->identity_attempts)],
            );
        }

        return response()->json(['ok' => true]);
    }

    /**
     * A assinatura: traço, visto, foto (obrigatória) e aceite, numa
     * requisição e numa transação — como no tablet.
     */
    public function sign(Request $request): JsonResponse
    {
        $solicitacao = $this->current($request);

        $dados = $request->validate([
            'signature' => ['required', 'string'],
            'strokes' => ['nullable', 'array'],
            'initials' => ['nullable', 'string'],
            'initials_strokes' => ['nullable', 'array'],
            'photo' => ['required', 'string'],
            'photo_consent' => ['required', 'accepted'],
            'accepted' => ['required', 'accepted'],
            'wants_copy' => ['nullable', 'boolean'],
            'read_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'scrolled_to_end' => ['nullable', 'boolean'],
            'viewport' => ['nullable', 'array'],
        ], [
            'photo.required' => 'A foto é obrigatória para assinar a distância.',
            'photo_consent.*' => 'É preciso autorizar a captura da imagem para assinar.',
            'accepted.*' => 'É preciso aceitar os termos do documento para assinar.',
        ]);

        try {
            $this->remote->sign($solicitacao, [
                'signature' => $dados['signature'],
                'strokes' => $dados['strokes'] ?? null,
                'initials' => $dados['initials'] ?? null,
                'initials_strokes' => $dados['initials_strokes'] ?? null,
                'photo' => $dados['photo'],
                'photo_consent' => true,
                'accepted' => true,
                'wants_copy' => (bool) ($dados['wants_copy'] ?? false),
                'read_seconds' => $dados['read_seconds'] ?? null,
                'scrolled_to_end' => (bool) ($dados['scrolled_to_end'] ?? false),
                'viewport' => $dados['viewport'] ?? null,
            ], $this->context($request));
        } catch (SignatureSessionException $e) {
            return $this->fail($e);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Assinatura registrada.',
            'signed_at' => now()->toIso8601String(),
            'validation_code' => $solicitacao->signer->document->validation_code,
        ]);
    }

    /** Os anexos pedidos e o que já foi enviado — também vem no objeto da sessão. */
    public function attachments(Request $request): JsonResponse
    {
        if ($recusa = $this->identityRequired($request)) {
            return $recusa;
        }

        return response()->json($this->remote->attachments($this->current($request)));
    }

    /**
     * Envia o arquivo de um item pedido (multipart: `file` + `key`). PDF, JPG
     * ou PNG, conferidos pelo conteúdo — a mesma regra do atendente.
     */
    public function storeAttachment(Request $request, SignatureAttachmentService $attachments): JsonResponse
    {
        if ($recusa = $this->identityRequired($request)) {
            return $recusa;
        }

        $solicitacao = $this->current($request);

        $dados = $request->validate([
            'file' => ['required', 'file', 'max:' . (int) config('signature.attachments.max_kb', 10240)],
            'key' => ['required', 'string', 'max:60'],
        ], [
            'file.required' => 'Escolha o arquivo.',
            'file.max' => 'O arquivo é grande demais.',
        ]);

        try {
            $attachments->store(
                $solicitacao->signer->document,
                $dados['file'],
                $dados['key'],
                via: $solicitacao,
                context: $this->context($request),
            );
        } catch (SignatureDocumentLockedException $e) {
            return response()->json(['error' => $e->getMessage(), 'reason' => 'attachment_refused'], 422);
        }

        return response()->json($this->remote->attachments($solicitacao->fresh()), 201);
    }

    /** Apaga um arquivo que a própria pessoa enviou, antes de assinar — é como ela troca. */
    public function destroyAttachment(Request $request, SignatureAttachmentService $attachments, int $anexo): JsonResponse
    {
        if ($recusa = $this->identityRequired($request)) {
            return $recusa;
        }

        $solicitacao = $this->current($request);
        $arquivo = SignatureAttachment::where('signature_document_id', $solicitacao->signer->signature_document_id)
            ->find($anexo);

        if (!$arquivo) {
            return response()->json(['error' => 'Anexo não encontrado.', 'reason' => 'attachment_refused'], 404);
        }

        try {
            $attachments->removeOwn($arquivo, $solicitacao, $this->context($request));
        } catch (SignatureDocumentLockedException $e) {
            return response()->json(['error' => $e->getMessage(), 'reason' => 'attachment_refused'], 403);
        }

        return response()->json($this->remote->attachments($solicitacao->fresh()));
    }

    /** Recusa, com motivo opcional: o documento inteiro vai para recusado. */
    public function refuse(Request $request): JsonResponse
    {
        $solicitacao = $this->current($request);

        $dados = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $this->remote->refuse($solicitacao, $dados['reason'] ?? null, $this->context($request));

        return response()->json(['ok' => true, 'message' => 'Assinatura recusada.']);
    }

    /** Sair sem assinar. O link continua valendo até vencer. */
    public function leave(Request $request): JsonResponse
    {
        $this->requests->cancel($this->current($request));

        return response()->json(['ok' => true]);
    }

    /**
     * A resposta de sessão do tablet, com o que o site precisa a mais: até
     * quando o link vale e quantas tentativas de CPF restam.
     *
     * @return array<string, mixed>
     */
    private function payload(SignatureRequest $solicitacao): array
    {
        $link = $solicitacao->remoteLink;

        return array_merge($this->sessionPayload($solicitacao), [
            // O texto da autorização de imagem é o que a evidência grava: o site
            // mostra ESTE, palavra por palavra, ao lado da caixa de marcar.
            'photo_consent_text' => \App\Models\SignatureEvidence::PHOTO_CONSENT_TEXT,
            // Só depois do CPF: antes dele, nem a lista do que se pede aparece.
            'attachments' => $solicitacao->identity_confirmed_at !== null
                ? $this->remote->attachments($solicitacao)
                : null,
            'link' => [
                'expires_at' => $link?->expires_at?->toIso8601String(),
                'identity_attempts_left' => max(0, SignatureCaptureService::MAX_IDENTITY_ATTEMPTS - (int) $link?->identity_attempts),
            ],
        ]);
    }

    /**
     * No balcão o atendente vê quem pega o tablet; aqui, quem tem o e-mail tem
     * o link. Por isso o CPF vem ANTES do documento: sem ele, o PDF, as
     * perguntas e a leitura ficam fechados (o e-mail diz isso à pessoa).
     */
    private function identityRequired(Request $request): ?JsonResponse
    {
        if ($this->current($request)->identity_confirmed_at !== null) {
            return null;
        }

        return response()->json([
            'error' => 'Confirme o seu CPF antes de abrir o documento.',
            'reason' => 'identity_required',
        ], 409);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function fail(SignatureSessionException $e, array $extra = []): JsonResponse
    {
        $reason = $e instanceof SignatureRemoteLinkException ? $e->reason : match ($e->status) {
            419 => 'session_ended',
            422 => 'invalid',
            429 => 'too_many_attempts',
            default => 'conflict',
        };

        return response()->json(array_merge(['error' => $e->getMessage(), 'reason' => $reason], $extra), $e->status);
    }

    /** @return array{ip: ?string, user_agent: ?string} */
    private function context(Request $request): array
    {
        return $request->attributes->get(EnsureSignatureRemoteClient::ATTRIBUTE, ['ip' => null, 'user_agent' => null]);
    }

    /** @return array<string, mixed> */
    private function auditContext(Request $request, SignatureRequest $solicitacao): array
    {
        return array_merge($this->context($request), [
            'signer' => $solicitacao->signature_signer_id,
            'request' => $solicitacao->id,
            'actor_type' => SignatureAuditEvent::ACTOR_KIOSK,
        ]);
    }

    private function current(Request $request): SignatureRequest
    {
        return $request->attributes->get(EnsureSignatureKioskSession::ATTRIBUTE);
    }
}
