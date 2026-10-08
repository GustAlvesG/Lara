<?php

namespace App\Http\Controllers\Signature;

use App\Exceptions\SignatureFormException;
use App\Exceptions\SignatureSessionException;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Signature\Concerns\RespondsWithKioskSession;
use App\Http\Middleware\EnsureSignatureKioskSession;
use App\Models\SignatureAuditEvent;
use App\Models\SignatureDocument;
use App\Models\SignatureRequest;
use App\Models\SignatureSigner;
use App\Services\Signature\SignatureCaptureService;
use App\Services\Signature\SignatureRequestService;
use App\Services\Signature\SignatureSigningDataService;
use App\Services\Signature\SignatureStateMachine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * O tablet do balcão. Rota pública, sem sessão web e sem menu.
 *
 * O aparelho não guarda nada: fica numa tela de espera com o leitor de QR
 * aberto e só sabe de um documento depois de ler um código que o atendente
 * acabou de gerar. Terminado o atendimento, a sessão morre e ele volta à
 * espera.
 *
 * Todo horário registrado é o do SERVIDOR. O relógio do tablet não entra em
 * nada — um aparelho de balcão passa meses sem sincronizar, e a hora da
 * assinatura é justamente o que precisa ser confiável.
 */
class QuiosqueController extends Controller
{
    use RespondsWithKioskSession;

    public function __construct(
        private SignatureRequestService $requests,
        private SignatureStateMachine $states,
        private SignatureCaptureService $capture,
        private SignatureSigningDataService $signingData,
    ) {
    }

    /** A tela do tablet — um arquivo só, sem build, como o kiosk de freelancer. */
    public function index()
    {
        return view('quiosque.index');
    }

    /**
     * Consome o token lido pela câmera e abre a sessão.
     *
     * O tablet manda o conteúdo CRU do QR; quem decide se aquilo é um código
     * deste sistema é o servidor. O leitor já ignora o que não começa com o
     * prefixo, mas repetir a checagem aqui é o que garante que um QR de outra
     * coisa não vire uma consulta ao banco.
     */
    public function consume(Request $request)
    {
        /*
         | Duas portas para a mesma liberação: o QR lido pela câmera e o código
         | DIGITADO, que é o caminho de quem não tem câmera — em HTTP comum,
         | `getUserMedia` não abre. Uma das duas é obrigatória, nunca as duas.
         */
        $request->validate([
            'payload' => ['required_without:code', 'nullable', 'string', 'max:200'],
            'code' => ['required_without:payload', 'nullable', 'string', 'max:40'],
        ]);

        try {
            if ($request->filled('code')) {
                $sessao = $this->requests->consumeManualCode(
                    $request->input('code'),
                    $request->ip(),
                    $request->userAgent(),
                );
            } else {
                $token = SignatureRequest::tokenFromQrPayload($request->input('payload'));

                if ($token === null) {
                    return response()->json([
                        'error' => 'Este QR Code não é de assinatura do Lara.',
                    ], 422);
                }

                $sessao = $this->requests->consume($token, $request->ip(), $request->userAgent());
            }
        } catch (SignatureSessionException $e) {
            return response()->json(['error' => $e->getMessage()], $e->status);
        }

        /*
         | A data da assinatura dos campos automáticos entra AGORA, antes de a
         | pessoa ver o documento — com a hora do servidor. O PDF que o tablet
         | vai buscar em seguida já é o datado.
         */
        $this->signingData->prepare(
            $sessao['request']->signer->document,
            $this->auditContext($request, $sessao['request']),
        );

        return response()
            ->json($this->sessionPayload($sessao['request']))
            ->cookie($this->sessionCookie($sessao['session_token']));
    }

    /**
     * As respostas de quem assina às perguntas do modelo.
     *
     * Vêm ANTES da leitura: o documento é remontado com elas, ganha hash novo,
     * e só então a pessoa lê — o texto que ela assina já tem as respostas
     * dela. Pode ser reenviado (a pessoa voltou para corrigir) até a primeira
     * assinatura.
     */
    public function answers(Request $request, SignatureDocument $signatureDocument)
    {
        $solicitacao = $this->current($request);

        $dados = $request->validate([
            'answers' => ['present', 'array', 'max:60'],
        ]);

        try {
            $this->signingData->answer(
                $signatureDocument,
                $dados['answers'],
                $this->auditContext($request, $solicitacao),
            );
        } catch (SignatureFormException $e) {
            return response()->json(['error' => $e->getMessage(), 'errors' => $e->errors], 422);
        } catch (SignatureSessionException $e) {
            return response()->json(['error' => $e->getMessage()], $e->status);
        }

        return response()->json($this->sessionPayload($solicitacao->fresh()));
    }

    /**
     * Estado da sessão — chamado pelo tablet a cada poucos segundos.
     *
     * Serve a duas coisas ao mesmo tempo: alimentar a contagem regressiva na
     * tela e derrubar o tablet quando o atendente cancela. Se o middleware
     * deixou passar, a sessão está viva.
     */
    public function session(Request $request)
    {
        return response()->json($this->sessionPayload($this->current($request)));
    }

    /**
     * O PDF original, para o PDF.js renderizar.
     *
     * É o MESMO arquivo cujo sha256 ficou gravado no congelamento. Nada é
     * remontado aqui: o que a pessoa lê na tela são os bytes que o manifesto e
     * a página de validação vão citar.
     */
    public function pdf(Request $request, SignatureDocument $signatureDocument)
    {
        // A sessão já foi conferida pelo middleware, inclusive a de que este
        // documento é o dela. Aqui só resta entregar os bytes.
        abort_if(!$signatureDocument->original_path, 404);

        $disk = Storage::disk(config('signature.disk'));

        abort_if(!$disk->exists($signatureDocument->original_path), 404);

        return $disk->response($signatureDocument->original_path, null, [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, max-age=0, no-store',
        ]);
    }

    /**
     * Registra que a pessoa leu o documento até o fim.
     *
     * A rolagem é conferida no NAVEGADOR; o servidor não tem como prová-la, e
     * seria desonesto dizer que tem. O que ele registra é o que recebeu, com a
     * hora dele: quanto tempo o documento ficou aberto e se a tela informou
     * que chegou ao fim. É evidência, não prova — e a diferença está escrita
     * no manifesto.
     */
    public function viewed(Request $request, SignatureDocument $signatureDocument)
    {
        $solicitacao = $this->current($request);

        $dados = $request->validate([
            'read_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'scrolled_to_end' => ['nullable', 'boolean'],
        ]);

        $this->states->note($signatureDocument->id, SignatureAuditEvent::EVENT_VIEWED, [
            'signer' => $solicitacao->signature_signer_id,
            'request' => $solicitacao->id,
            'actor_type' => SignatureAuditEvent::ACTOR_KIOSK,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'payload' => [
                'segundos_de_leitura' => $dados['read_seconds'] ?? null,
                'rolou_ate_o_fim' => (bool) ($dados['scrolled_to_end'] ?? false),
            ],
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * Conferência de identidade.
     *
     * O que a pessoa digita vai para o servidor; o CPF cadastrado não vem para
     * a tela em hipótese nenhuma. A resposta de erro diz só que não conferiu —
     * apontar qual dígito falhou transformaria a etapa num formulário de
     * adivinhação.
     */
    public function identity(Request $request, SignatureDocument $signatureDocument)
    {
        $solicitacao = $this->current($request);

        // `cpf` nos modos de CPF; `codigo` no de código enviado por e-mail.
        $dados = $request->validate([
            'cpf' => ['required_without:codigo', 'nullable', 'string', 'max:20'],
            'codigo' => ['required_without:cpf', 'nullable', 'string', 'max:10'],
        ]);

        try {
            $this->capture->confirmIdentity($solicitacao, $dados['cpf'] ?? $dados['codigo'], [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (SignatureSessionException $e) {
            return response()->json(['error' => $e->getMessage()], $e->status);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Envia o código da conferência de identidade ao e-mail do signatário —
     * modelo com a opção "Código enviado por e-mail". O tablet chama ao abrir a
     * etapa e no "Reenviar código". O endereço não vem para a tela.
     */
    public function identityCode(Request $request, SignatureDocument $signatureDocument)
    {
        $solicitacao = $this->current($request);

        try {
            $this->capture->sendIdentityCode($solicitacao, [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (SignatureSessionException $e) {
            return response()->json(['error' => $e->getMessage()], $e->status);
        } catch (\Throwable $e) {
            // SMTP fora do ar: nada ficou registrado como enviado.
            \Illuminate\Support\Facades\Log::error('Falha ao enviar o código de identidade.', [
                'request_id' => $solicitacao->id,
                'erro' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Não foi possível enviar o código agora. Chame o atendente.'], 503);
        }

        return response()->json([
            'ok' => true,
            'resend_seconds' => \App\Services\Signature\SignatureCaptureService::CODE_RESEND_SECONDS,
            'sends_left' => \App\Services\Signature\SignatureCaptureService::MAX_CODE_SENDS - $solicitacao->identity_code_sends,
        ]);
    }

    /**
     * A assinatura. Uma requisição, uma transação, um resultado.
     *
     * O tablet manda tudo de uma vez — traço, traços vetoriais, foto, aceite e
     * tempo de leitura — porque é assim que a gravação cabe numa transação só.
     * Mandar por partes deixaria o atendimento "meio assinado" quando a rede
     * caísse no meio, e assinatura não se repete.
     */
    public function sign(Request $request, SignatureDocument $signatureDocument)
    {
        $solicitacao = $this->current($request);

        $dados = $request->validate([
            'signature' => ['required', 'string'],
            'strokes' => ['nullable', 'array'],
            // O visto de todas as páginas — exigido quando o modelo pede.
            'initials' => ['nullable', 'string'],
            'initials_strokes' => ['nullable', 'array'],
            'photo' => ['nullable', 'string'],
            // Por que não veio foto. Só `camera_unavailable` é aceito, e só
            // com a flag ligada — ver SignatureCaptureService.
            'photo_skipped_reason' => ['nullable', 'string', 'max:60'],
            // Autorização da captura da imagem, marcada na tela de aceite.
            // Exigida quando há foto — ver SignatureCaptureService.
            'photo_consent' => ['nullable', 'boolean'],
            'accepted' => ['required', 'accepted'],
            'wants_copy' => ['nullable', 'boolean'],
            'read_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'scrolled_to_end' => ['nullable', 'boolean'],
            'viewport' => ['nullable', 'array'],
        ]);

        try {
            $this->capture->capture($solicitacao, [
                'signature' => $dados['signature'],
                'strokes' => $dados['strokes'] ?? null,
                'initials' => $dados['initials'] ?? null,
                'initials_strokes' => $dados['initials_strokes'] ?? null,
                'photo' => $dados['photo'] ?? null,
                'photo_skipped_reason' => $dados['photo_skipped_reason'] ?? null,
                'photo_consent' => (bool) ($dados['photo_consent'] ?? false),
                'accepted' => true,
                'wants_copy' => (bool) ($dados['wants_copy'] ?? false),
                'read_seconds' => $dados['read_seconds'] ?? null,
                'scrolled_to_end' => (bool) ($dados['scrolled_to_end'] ?? false),
                'viewport' => $dados['viewport'] ?? null,
            ], [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (SignatureSessionException $e) {
            return response()->json(['error' => $e->getMessage()], $e->status);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Assinatura registrada.',
            'signed_at' => now()->format('d/m/Y H:i:s'),
        ])->withCookie($this->forgetCookie());
    }

    /**
     * Recusa — disponível em qualquer etapa, com motivo opcional.
     *
     * Recusar não é erro nem abandono: é uma resposta, e o documento inteiro
     * vai para `refused`. Tentar seguir com os demais signatários depois de
     * uma recusa produziria um documento que ninguém pediu.
     */
    public function refuse(Request $request, SignatureDocument $signatureDocument)
    {
        $solicitacao = $this->current($request);

        $dados = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $signatario = $solicitacao->signer;

        $this->states->signerTo(
            $signatario,
            SignatureSigner::STATUS_REFUSED,
            SignatureAuditEvent::EVENT_REFUSED,
            ['refused_at' => now(), 'refusal_reason' => $dados['reason'] ?? null],
            [
                'request' => $solicitacao->id,
                'actor_type' => SignatureAuditEvent::ACTOR_KIOSK,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'payload' => ['motivo' => $dados['reason'] ?? null],
            ],
        );

        $this->states->documentTo(
            $signatureDocument,
            SignatureDocument::STATUS_REFUSED,
            SignatureAuditEvent::EVENT_REFUSED,
            [],
            [
                'signer' => $signatario->id,
                'actor_type' => SignatureAuditEvent::ACTOR_KIOSK,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ],
        );

        $this->requests->complete($solicitacao, [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json(['ok' => true, 'message' => 'Assinatura recusada.'])
            ->withCookie($this->forgetCookie());
    }

    /**
     * Encerra a sessão e volta à tela de espera — o botão "sair" e o fim do
     * tempo de inatividade.
     */
    public function leave(Request $request)
    {
        $solicitacao = $this->current($request);

        $this->requests->cancel($solicitacao);

        return response()->json(['ok' => true])->withCookie($this->forgetCookie());
    }

    /**
     * @return array<string, mixed>
     */
    private function auditContext(Request $request, SignatureRequest $solicitacao): array
    {
        return [
            'signer' => $solicitacao->signature_signer_id,
            'request' => $solicitacao->id,
            'actor_type' => SignatureAuditEvent::ACTOR_KIOSK,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];
    }

    /**
     * A solicitação que o middleware já resolveu e conferiu.
     *
     * O controller nunca lê o cookie: quando chega aqui, a sessão já foi
     * validada e amarrada ao documento certo.
     */
    private function current(Request $request): SignatureRequest
    {
        return $request->attributes->get(EnsureSignatureKioskSession::ATTRIBUTE);
    }
}
