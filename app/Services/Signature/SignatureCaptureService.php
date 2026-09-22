<?php

namespace App\Services\Signature;

use App\Exceptions\SignatureSessionException;
use App\Models\SignatureAuditEvent;
use App\Models\SignatureDocument;
use App\Models\SignatureEvidence;
use App\Models\SignatureRequest;
use App\Models\SignatureSigner;
use App\Support\Cpf;
use Illuminate\Support\Facades\DB;
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
    /** Tentativas de CPF antes de a sessão ser encerrada. */
    private const MAX_IDENTITY_ATTEMPTS = 5;

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

        if (!Cpf::matches($typed, $signer->cpf, $modo)) {
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

        $request->forceFill(['identity_confirmed_at' => now()])->save();

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
     * Grava a assinatura e as evidências.
     *
     * @param  array{
     *     signature: string,
     *     strokes?: array<mixed>|null,
     *     photo?: string|null,
     *     accepted: bool,
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

        $foto = null;

        if ($template->requires_photo) {
            $foto = $this->decodeImage(
                $payload['photo'] ?? '',
                ['jpeg', 'png'],
                (int) config('signature.evidence.max_photo_kb', 4096),
                'foto',
            );
        }

        $disk = Storage::disk(config('signature.disk'));

        $caminhoAssinatura = config('signature.paths.signatures') . '/' . $document->id
            . '/signer_' . $signer->id . '.png';

        $disk->put($caminhoAssinatura, $assinatura);

        $caminhoFoto = null;

        if ($foto !== null) {
            $caminhoFoto = config('signature.paths.photos') . '/' . $document->id
                . '/signer_' . $signer->id . '.jpg';

            $disk->put($caminhoFoto, $foto);
        }

        try {
            return DB::transaction(function () use (
                $request,
                $signer,
                $document,
                $payload,
                $context,
                $tracos,
                $caminhoAssinatura,
                $caminhoFoto,
            ) {
                SignatureEvidence::create([
                    'signature_signer_id' => $signer->id,
                    'signature_path' => $caminhoAssinatura,
                    'strokes' => $tracos,
                    'photo_path' => $caminhoFoto,
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
                    ['signed_at' => now()],
                    [
                        'request' => $request->id,
                        'actor_type' => SignatureAuditEvent::ACTOR_KIOSK,
                        'ip' => $context['ip'] ?? null,
                        'user_agent' => $context['user_agent'] ?? null,
                        'payload' => [
                            'segundos_de_leitura' => $payload['read_seconds'] ?? null,
                            'rolou_ate_o_fim' => (bool) ($payload['scrolled_to_end'] ?? false),
                            'com_foto' => $caminhoFoto !== null,
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
                }

                return $signer->fresh();
            });
        } catch (\Throwable $e) {
            // Disco não participa de rollback: o que a transação não gravou
            // não pode ficar no disco.
            $disk->delete(array_filter([$caminhoAssinatura, $caminhoFoto]));

            throw $e;
        }
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
