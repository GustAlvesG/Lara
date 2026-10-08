<?php

namespace App\Services\Signature;

use App\Exceptions\SignatureSessionException;
use App\Jobs\FinalizeSignatureDocument;
use App\Mail\SignatureIdentityCodeMail;
use App\Models\SignatureAuditEvent;
use App\Models\SignatureDocument;
use App\Models\SignatureEvidence;
use App\Models\SignatureRequest;
use App\Models\SignatureSigner;
use App\Models\SignatureTemplate;
use App\Support\Cpf;
use App\Support\EmailMask;
use App\Support\PngTrimmer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * O ato de assinar: conferência de identidade, traço, foto e evidências.
 *
 * Duas garantias moram aqui, e são as que o tablet não pode dar sozinho.
 *
 * **A gravação é uma transação só.** Assinatura, evidência, estado do
 * signatário, estado do documento e fim da sessão entram juntos ou não entram.
 * Uma queda de rede no meio não deixa um atendimento "meio assinado" — o
 * tablet reenvia e o servidor responde o mesmo resultado.
 *
 * **O que vale é a hora do servidor.** Um tablet de balcão passa meses sem
 * sincronizar o relógio, e a hora da assinatura é justamente o que precisa ser
 * confiável.
 *
 * Os arquivos são gravados ANTES da transação e apagados se ela falhar: disco
 * não participa de rollback, e o inverso (transação primeiro) deixaria uma
 * assinatura gravada sem o traço — irrecuperável, porque assinatura não se
 * repete. É a mesma ordem usada na assinatura de contrato de freelancer.
 */
class SignatureCaptureService
{
    /** Tentativas de CPF (ou de código) antes de a sessão ser encerrada. */
    private const MAX_IDENTITY_ATTEMPTS = 5;

    /** Envios do código por e-mail numa mesma liberação. */
    public const MAX_CODE_SENDS = 3;

    /** Espera mínima, em segundos, entre dois envios do código. */
    public const CODE_RESEND_SECONDS = 60;

    public function __construct(
        private SignatureStateMachine $states,
        private SignatureRequestService $requests,
    ) {
    }

    /**
     * Confere o CPF digitado no tablet, no SERVIDOR.
     *
     * O CPF cadastrado nunca sai daqui — nem na resposta de erro, que diz
     * apenas que não conferiu. Dizer "faltou um dígito" ou mostrar os
     * primeiros números transformaria a conferência num formulário de
     * adivinhação.
     *
     * @param  array<string, mixed>  $context
     *
     * @throws SignatureSessionException
     */
    public function confirmIdentity(SignatureRequest $request, ?string $typed, array $context = []): SignatureRequest
    {
        $signer = $request->signer;
        $modo = $signer->document->template->identity_check;

        if ($request->identity_attempts >= self::MAX_IDENTITY_ATTEMPTS) {
            $this->requests->cancel($request);

            throw new SignatureSessionException(
                'Número de tentativas excedido. Peça ao atendente que gere um novo QR Code.',
                429,
            );
        }

        $confere = $modo === SignatureTemplate::IDENTITY_EMAIL
            ? $this->codeMatches($request, $typed)
            : Cpf::matches($typed, $signer->cpf, $modo);

        if (!$confere) {
            $request->forceFill(['identity_attempts' => $request->identity_attempts + 1])->save();

            $this->states->note($signer->signature_document_id, SignatureAuditEvent::EVENT_IDENTITY_FAILED, [
                'signer' => $signer->id,
                'request' => $request->id,
                'actor_type' => SignatureAuditEvent::ACTOR_KIOSK,
                'ip' => $context['ip'] ?? null,
                'user_agent' => $context['user_agent'] ?? null,
                'payload' => ['tentativa' => $request->identity_attempts],
            ]);

            $restantes = self::MAX_IDENTITY_ATTEMPTS - $request->identity_attempts;

            throw new SignatureSessionException(
                'Os dados não conferem. ' . ($restantes > 0
                    ? 'Tente novamente (' . $restantes . ' tentativa(s) restante(s)).'
                    : 'Peça ao atendente que gere um novo QR Code.'),
                422,
            );
        }

        // Código usado não vale de novo.
        $request->forceFill([
            'identity_confirmed_at' => now(),
            'identity_code_hash' => null,
            'identity_code_expires_at' => null,
        ])->save();

        $this->states->note($signer->signature_document_id, SignatureAuditEvent::EVENT_IDENTITY_CONFIRMED, [
            'signer' => $signer->id,
            'request' => $request->id,
            'actor_type' => SignatureAuditEvent::ACTOR_KIOSK,
            'ip' => $context['ip'] ?? null,
            'user_agent' => $context['user_agent'] ?? null,
            'payload' => ['modo' => $modo],
        ]);

        return $request;
    }

    /**
     * Envia ao e-mail do signatário o código da conferência de identidade —
     * para o modelo com a opção "Código enviado por e-mail".
     *
     * Um código vivo por liberação: reenviar troca o anterior. O banco guarda
     * só o HMAC (com a chave do app e o id da liberação), porque 6 números em
     * sha256 puro se descobrem em segundos. O e-mail sai na hora e fora da
     * fila: a pessoa espera no balcão, e o código em claro não pode ficar na
     * tabela de jobs. Até MAX_CODE_SENDS envios, com CODE_RESEND_SECONDS entre
     * eles.
     *
     * @param  array<string, mixed>  $context
     *
     * @throws SignatureSessionException
     */
    public function sendIdentityCode(SignatureRequest $request, array $context = []): SignatureRequest
    {
        $signer = $request->signer;

        if ($signer->document->template->identity_check !== SignatureTemplate::IDENTITY_EMAIL) {
            throw new SignatureSessionException('Este documento não usa código por e-mail.', 422);
        }

        if (!$signer->email) {
            throw new SignatureSessionException('Não há e-mail cadastrado para enviar o código. Chame o atendente.', 422);
        }

        if ($request->identity_confirmed_at !== null) {
            throw new SignatureSessionException('A identidade já foi confirmada.', 409);
        }

        if ($request->identity_code_sends >= self::MAX_CODE_SENDS) {
            throw new SignatureSessionException('O código já foi enviado ' . self::MAX_CODE_SENDS . ' vezes. Chame o atendente.', 429);
        }

        if ($request->identity_code_sent_at && $request->identity_code_sent_at->diffInSeconds(now()) < self::CODE_RESEND_SECONDS) {
            throw new SignatureSessionException('Aguarde um minuto antes de pedir outro código.', 429);
        }

        $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $minutos = (int) config('signature.identity_code_ttl_minutes', 10);

        DB::transaction(function () use ($request, $signer, $codigo, $minutos, $context) {
            $request->forceFill([
                'identity_code_hash' => $this->codeHash($request, $codigo),
                'identity_code_expires_at' => now()->addMinutes($minutos),
                'identity_code_sent_at' => now(),
                'identity_code_sends' => $request->identity_code_sends + 1,
            ])->save();

            // Dentro da transação: SMTP fora do ar desfaz o registro do envio.
            Mail::to($signer->email)->send(new SignatureIdentityCodeMail($signer, $codigo, $minutos));

            $this->states->note($signer->signature_document_id, SignatureAuditEvent::EVENT_IDENTITY_CODE_SENT, [
                'signer' => $signer->id,
                'request' => $request->id,
                'actor_type' => SignatureAuditEvent::ACTOR_KIOSK,
                'ip' => $context['ip'] ?? null,
                'user_agent' => $context['user_agent'] ?? null,
                'payload' => ['email' => EmailMask::of($signer->email), 'envio' => $request->identity_code_sends],
            ]);
        });

        return $request;
    }

    /** O código digitado é o vivo desta liberação, dentro do prazo? */
    private function codeMatches(SignatureRequest $request, ?string $typed): bool
    {
        $digitos = preg_replace('/\D/', '', (string) $typed);

        return $request->identity_code_hash !== null
            && $request->identity_code_expires_at?->isFuture()
            && strlen($digitos) === 6
            && hash_equals($request->identity_code_hash, $this->codeHash($request, $digitos));
    }

    private function codeHash(SignatureRequest $request, string $code): string
    {
        return hash_hmac('sha256', $request->id . ':' . $code, (string) config('app.key'));
    }

    /**
     * Grava a assinatura e as evidências.
     *
     * @param  array{
     *     signature: string,
     *     strokes?: array<mixed>|null,
     *     photo?: string|null,
     *     photo_consent?: bool,
     *     accepted: bool,
     *     wants_copy?: bool,
     *     read_seconds?: int|null,
     *     scrolled_to_end?: bool,
     *     viewport?: array<string, mixed>|null,
     * }  $payload
     * @param  array{ip?: ?string, user_agent?: ?string}  $context
     *
     * @throws SignatureSessionException
     */
    public function capture(SignatureRequest $request, array $payload, array $context = []): SignatureSigner
    {
        $signer = $request->signer;
        $document = $signer->document;
        $template = $document->template;

        /*
         | Reenvio depois de uma falha de rede: a assinatura já entrou. Devolve
         | o mesmo resultado em vez de recusar — para quem está no balcão, o
         | ato aconteceu, e um erro aqui mandaria assinar de novo o que já está
         | assinado.
         */
        if ($signer->status === SignatureSigner::STATUS_SIGNED) {
            return $signer;
        }

        if ($request->identity_confirmed_at === null) {
            throw new SignatureSessionException('Confirme a identidade antes de assinar.', 409);
        }

        // O modelo pergunta algo a quem assina e a resposta não veio: o texto
        // ainda tem lacunas, e lacuna não se assina. O tablet não chega aqui
        // pelo caminho normal — a trava é para o caminho que não é normal.
        if ($document->signingFormPending()) {
            throw new SignatureSessionException('Responda as perguntas do documento antes de assinar.', 409);
        }

        if (!($payload['accepted'] ?? false)) {
            throw new SignatureSessionException('É preciso aceitar os termos do documento para assinar.', 422);
        }

        $assinatura = $this->decodeImage(
            $payload['signature'] ?? '',
            ['png'],
            (int) config('signature.evidence.max_signature_kb', 2048),
            'assinatura',
        );

        $tracos = $payload['strokes'] ?? null;
        $pontos = SignatureEvidence::countPoints($tracos);
        $minimo = (int) config('signature.evidence.min_stroke_points', 30);

        /*
         | Um toque na tela não é assinatura. O traço vetorial é o que separa
         | um rabisco de um encostar de dedo — e, mais tarde, uma assinatura
         | feita à mão de uma imagem colada, que não tem movimento nenhum.
         */
        if ($pontos < $minimo) {
            throw new SignatureSessionException(
                'A assinatura ficou muito curta. Assine novamente no espaço indicado.',
                422,
            );
        }

        /*
         | O visto de todas as páginas. É exigido pelo MODELO — e conferido
         | aqui, como a foto: se dependesse do tablet mandar ou não, a
         | exigência seria uma sugestão.
         */
        $visto = null;
        $tracosDoVisto = null;

        if ($template->requires_initials) {
            $tracosDoVisto = $payload['initials_strokes'] ?? null;

            if (($payload['initials'] ?? '') === ''
                || SignatureEvidence::countPoints($tracosDoVisto) < (int) config('signature.evidence.min_initials_points', 8)) {
                throw new SignatureSessionException(
                    'Faltou o visto. Faça a sua rubrica no espaço indicado.',
                    422,
                );
            }

            $visto = PngTrimmer::trim($this->decodeImage(
                $payload['initials'],
                ['png'],
                (int) config('signature.evidence.max_signature_kb', 2048),
                'rubrica',
            ));
        }

        $foto = null;
        $motivoSemFoto = null;

        if ($template->requires_photo) {
            if (($payload['photo'] ?? '') === '') {
                // Sem câmera (ambiente sem HTTPS): a evidência fica registrada
                // como AUSENTE, com o motivo — nunca silenciada.
                $motivoSemFoto = $this->photoSkipReason($payload['photo_skipped_reason'] ?? null);
            } else {
                /*
                 | A foto só entra com a autorização de quem aparece nela,
                 | marcada na tela de aceite. Conferida aqui pelo mesmo motivo
                 | do aceite dos termos: se dependesse do tablet, seria uma
                 | sugestão. Sem câmera não há foto, e não há o que autorizar.
                 */
                if (!($payload['photo_consent'] ?? false)) {
                    throw new SignatureSessionException(
                        'É preciso autorizar a captura da imagem para assinar.',
                        422,
                    );
                }

                $foto = $this->decodeImage(
                    $payload['photo'],
                    ['jpeg', 'png'],
                    (int) config('signature.evidence.max_photo_kb', 4096),
                    'foto',
                );
            }
        }

        $disk = Storage::disk(config('signature.disk'));

        $caminhoAssinatura = config('signature.paths.signatures') . '/' . $document->id
            . '/signer_' . $signer->id . '.png';

        $caminhoVisto = $visto !== null
            ? config('signature.paths.signatures') . '/' . $document->id . '/signer_' . $signer->id . '_visto.png'
            : null;

        $caminhoFoto = $foto !== null
            ? config('signature.paths.photos') . '/' . $document->id . '/signer_' . $signer->id . '.jpg'
            : null;

        /*
         | O disco `local` tem `throw => false`: sem permissão na pasta, o put
         | devolve false calado — e a assinatura seria registrada sem o traço,
         | e a finalização quebraria depois, longe daqui. Confere cada gravação
         | e recusa enquanto a pessoa ainda está no balcão.
         */
        $gravados = [];

        foreach (array_filter([
            $caminhoAssinatura => $assinatura,
            $caminhoVisto => $visto,
            $caminhoFoto => $foto,
        ], fn($bytes, $caminho) => $caminho !== '' && $bytes !== null, ARRAY_FILTER_USE_BOTH) as $caminho => $bytes) {
            if (!$disk->put($caminho, $bytes)) {
                $disk->delete($gravados);

                Log::error('Assinatura no tablet: não foi possível gravar a evidência no disco.', [
                    'document_id' => $document->id,
                    'signer_id' => $signer->id,
                    'caminho' => $caminho,
                    'disco' => config('signature.disk'),
                ]);

                throw new SignatureSessionException(
                    'Não foi possível gravar a assinatura no servidor. Chame o atendente.',
                    500,
                );
            }

            $gravados[] = $caminho;
        }

        $fechouODocumento = false;

        try {
            $assinado = DB::transaction(function () use (
                &$fechouODocumento,
                $request,
                $signer,
                $document,
                $payload,
                $context,
                $tracos,
                $caminhoAssinatura,
                $caminhoFoto,
                $motivoSemFoto,
                $caminhoVisto,
                $tracosDoVisto,
            ) {
                SignatureEvidence::create([
                    'signature_signer_id' => $signer->id,
                    'signature_path' => $caminhoAssinatura,
                    'initials_path' => $caminhoVisto,
                    'initials_strokes' => $caminhoVisto !== null ? $tracosDoVisto : null,
                    'strokes' => $tracos,
                    'photo_path' => $caminhoFoto,
                    'photo_skipped_reason' => $motivoSemFoto,
                    // A autorização vale para a foto que foi guardada — e o
                    // texto é o que estava na tela, não o de hoje.
                    'photo_consent' => $caminhoFoto !== null,
                    'photo_consent_text' => $caminhoFoto !== null ? SignatureEvidence::PHOTO_CONSENT_TEXT : null,
                    'ip' => $context['ip'] ?? null,
                    'user_agent' => isset($context['user_agent'])
                        ? mb_substr((string) $context['user_agent'], 0, 255)
                        : null,
                    'read_seconds' => $payload['read_seconds'] ?? null,
                    'scrolled_to_end' => (bool) ($payload['scrolled_to_end'] ?? false),
                    'accepted' => true,
                    'viewport' => $payload['viewport'] ?? null,
                    // Hora do SERVIDOR. O relógio do tablet não entra em nada.
                    'server_signed_at' => now(),
                ]);

                $this->states->signerTo(
                    $signer,
                    SignatureSigner::STATUS_SIGNED,
                    SignatureAuditEvent::EVENT_SIGNED,
                    [
                        'signed_at' => now(),
                        // Pedido da via, marcado na tela de aceite. Chega junto
                        // com a assinatura porque a sessão do tablet morre no
                        // instante em que ela entra — perguntar depois exigiria
                        // manter viva uma sessão que já não tem o que fazer.
                        'wants_copy' => (bool) ($payload['wants_copy'] ?? false) && $signer->email !== null,
                    ],
                    [
                        'request' => $request->id,
                        'actor_type' => SignatureAuditEvent::ACTOR_KIOSK,
                        'ip' => $context['ip'] ?? null,
                        'user_agent' => $context['user_agent'] ?? null,
                        'payload' => [
                            'segundos_de_leitura' => $payload['read_seconds'] ?? null,
                            'rolou_ate_o_fim' => (bool) ($payload['scrolled_to_end'] ?? false),
                            'com_foto' => $caminhoFoto !== null,
                            'autorizou_imagem' => $caminhoFoto !== null,
                            'com_visto' => $caminhoVisto !== null,
                            'foto_ausente' => $motivoSemFoto,
                        ],
                    ],
                );

                $this->requests->complete($request, [
                    'ip' => $context['ip'] ?? null,
                    'user_agent' => $context['user_agent'] ?? null,
                ]);

                /*
                 | Último da fila: o documento inteiro passa a assinado. Com
                 | mais gente pendente, ele continua aguardando — e o atendente
                 | gera o próximo QR.
                 */
                if ($document->fresh()->allSignersSigned()) {
                    $this->states->documentTo(
                        $document,
                        SignatureDocument::STATUS_SIGNED,
                        SignatureAuditEvent::EVENT_SIGNED,
                        [],
                        ['actor_type' => SignatureAuditEvent::ACTOR_KIOSK],
                    );

                    $fechouODocumento = true;
                }

                return $signer->fresh();
            });
        } catch (\Throwable $e) {
            // Disco não participa de rollback: o que a transação não gravou
            // não pode ficar no disco.
            $disk->delete(array_filter([$caminhoAssinatura, $caminhoFoto, $caminhoVisto]));

            throw $e;
        }

        /*
         | O PDF final é montado FORA da transação, em fila: gerar PDF leva
         | segundos e a pessoa está no balcão. Despachar de dentro da transação
         | correria o risco de o worker pegar o job antes do commit e não achar
         | o documento assinado.
         */
        if ($fechouODocumento) {
            FinalizeSignatureDocument::dispatch($document->id);
        }

        return $assinado;
    }

    /**
     * O modelo pede foto e ela não veio. Isso só é aceito num caso, e com a
     * flag ligada: o aparelho não tem câmera — o que, na prática, quer dizer
     * ambiente sem HTTPS, onde `getUserMedia` não existe.
     *
     * Qualquer outro motivo é recusado com a mesma mensagem de sempre. Aceitar
     * um motivo livre transformaria a exigência de foto em sugestão: bastaria
     * o cliente mandar qualquer string.
     *
     * @throws SignatureSessionException
     */
    private function photoSkipReason(?string $reason): string
    {
        if (!config('signature.evidence.skip_photo_without_camera', false)
            || $reason !== SignatureEvidence::PHOTO_SKIP_NO_CAMERA) {
            throw new SignatureSessionException('Não foi possível ler a foto. Tente novamente.', 422);
        }

        return SignatureEvidence::PHOTO_SKIP_NO_CAMERA;
    }

    /**
     * Decodifica uma data URL e confere o tipo PELOS BYTES.
     *
     * O cabeçalho do data URL é escrito por quem envia e não prova nada — a
     * mesma trava do cadastro de foto de freelancer, onde já se tentou subir
     * conteúdo que não era imagem com cabeçalho de imagem.
     *
     * @param  array<int, string>  $permitidos
     *
     * @throws SignatureSessionException
     */
    private function decodeImage(string $dataUrl, array $permitidos, int $maxKb, string $rotulo): string
    {
        if ($dataUrl === '' || !preg_match('#^data:image/(png|jpeg|jpg);base64,#i', $dataUrl)) {
            throw new SignatureSessionException('Não foi possível ler a ' . $rotulo . '. Tente novamente.', 422);
        }

        $binario = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1), true);

        if ($binario === false || $binario === '') {
            throw new SignatureSessionException('Não foi possível ler a ' . $rotulo . '. Tente novamente.', 422);
        }

        if (strlen($binario) > $maxKb * 1024) {
            throw new SignatureSessionException(
                'A ' . $rotulo . ' ficou grande demais (limite de ' . $maxKb . ' KB).',
                422,
            );
        }

        $tipo = $this->sniffImageType($binario);

        if ($tipo === null || !in_array($tipo, $permitidos, true)) {
            throw new SignatureSessionException('O arquivo enviado não é uma imagem válida.', 422);
        }

        return $binario;
    }

    /** Assinatura binária do arquivo: PNG e JPEG têm cabeçalhos próprios. */
    private function sniffImageType(string $binario): ?string
    {
        if (str_starts_with($binario, "\x89PNG\r\n\x1a\n")) {
            return 'png';
        }

        if (str_starts_with($binario, "\xFF\xD8\xFF")) {
            return 'jpeg';
        }

        return null;
    }

}
