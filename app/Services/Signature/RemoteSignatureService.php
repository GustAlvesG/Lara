<?php

namespace App\Services\Signature;

use App\Exceptions\SignatureDocumentLockedException;
use App\Exceptions\SignatureRemoteLinkException;
use App\Exceptions\SignatureSessionException;
use App\Mail\SignatureRemoteLinkMail;
use App\Models\SignatureAuditEvent;
use App\Models\SignatureDocument;
use App\Models\SignatureRemoteLink;
use App\Models\SignatureRequest;
use App\Models\SignatureSigner;
use App\Support\Cpf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Assinatura remota: o link enviado por e-mail que abre a tela de quem assina
 * no site externo (Next.js, na DMZ), sem login.
 *
 * O Lara continua fechado. O navegador da pessoa fala com o site; o SERVIDOR
 * do site fala com a API `/api/assinatura/remota/*`, com token próprio. Tudo o
 * que o tablet garante continua valendo, porque a captura é a MESMA
 * (SignatureCaptureService): hora do servidor, transação única, traço mínimo,
 * hash do que foi lido. O que muda é como a pessoa chega ao documento, e
 * isso aperta três coisas:
 *
 *  - identidade sempre pelo CPF COMPLETO, qualquer que seja a regra do modelo;
 *  - foto sempre obrigatória (não há atendente vendo quem assina);
 *  - as 5 tentativas de CPF contam no LINK, somadas entre as aberturas —
 *    abrir de novo não devolve tentativa. Esgotadas, o link é bloqueado.
 *
 * O link vale `remote.link_ttl_hours` e pode ser aberto várias vezes até a
 * pessoa assinar ou recusar: cada abertura vira uma liberação
 * (`signature_requests`, `identity_method = link_email`) com sessão curta,
 * consumida na hora — o segredo dela nunca sai do servidor.
 *
 * Tablet e link convivem no mesmo documento: a captura é a mesma, e o PDF
 * final é montado do mesmo jeito. O gov.br continua exclusivo — preparar o
 * documento para o gov.br revoga os links ativos.
 */
class RemoteSignatureService
{
    public function __construct(
        private SignatureStateMachine $states,
        private SignatureRequestService $requests,
        private SignatureCaptureService $capture,
        private SignatureSigningDataService $signingData,
    ) {
    }

    /** O botão aparece no painel? Sem o endereço do site não há link a montar. */
    public function enabled(): bool
    {
        return config('signature.remote.site_url', '') !== '';
    }

    /**
     * Por que este signatário não pode receber o link agora. null = pode.
     */
    public function blockReason(SignatureSigner $signer): ?string
    {
        if (!$this->enabled()) {
            return 'A assinatura remota não está configurada: falta o endereço do site (SIGNATURE_REMOTE_SITE_URL).';
        }

        if ($signer->isDirector()) {
            return 'Diretor assina no tablet, em "Assinatura da Diretoria", com a matrícula e o PIN do Lara — não por link.';
        }

        $document = $signer->document;

        if ($document?->isMinorTerm()) {
            return 'O Termo de Menores é assinado no tablet do evento, não por link.';
        }

        if ($motivo = $signer->releaseBlockReason()) {
            return $motivo;
        }

        // A conferência remota é sempre pelo CPF completo: sem um CPF válido
        // não há o que conferir.
        if (!Cpf::isValid($signer->cpf)) {
            return 'O CPF de ' . $signer->name . ' não é válido: a assinatura remota confere o CPF completo.';
        }

        return null;
    }

    /**
     * Envia o link por e-mail e devolve o registro. O e-mail informado passa
     * a ser o do signatário — é para ele que a via final vai, depois.
     *
     * Enviar de novo substitui o link anterior. O e-mail sai NA HORA, dentro
     * da transação: se o envio falhar, nada fica registrado, e o atendente vê
     * o erro no mesmo clique. O token em claro existe só no e-mail e em
     * `plainUrl` do objeto devolvido, para a tela mostrar uma vez ao atendente.
     *
     * @param  ?string  $replyTo  e-mail do atendente: a resposta ao e-mail vai para ele
     *
     * @throws SignatureDocumentLockedException
     */
    public function send(
        SignatureSigner $signer,
        string $email,
        ?int $userId = null,
        ?string $userName = null,
        ?string $replyTo = null,
    ): SignatureRemoteLink {
        if ($motivo = $this->blockReason($signer)) {
            throw new SignatureDocumentLockedException($motivo);
        }

        $email = mb_strtolower(trim($email));
        $replyTo = filter_var($replyTo, FILTER_VALIDATE_EMAIL) ? $replyTo : null;
        $document = $signer->document;

        return DB::transaction(function () use ($signer, $document, $email, $replyTo, $userId, $userName) {
            $substituidos = $this->supersedeActive($signer, $userId, $userName);

            $token = Str::random(64);
            $vence = now()->addHours(max(1, (int) config('signature.remote.link_ttl_hours', 72)));

            /*
             | O prazo do balcão é de horas (`document_ttl_hours`), e o link vale
             | dias. Sem estender, o documento expiraria com o link ainda no
             | e-mail da pessoa. Só estende: um prazo maior (gov.br, diretoria)
             | fica como está.
             */
            $prazoAnterior = $document->expires_at;
            $estendeu = $prazoAnterior === null || $prazoAnterior->lt($vence);

            if ($estendeu) {
                $document->forceFill(['expires_at' => $vence])->save();
            }

            if ($signer->email !== $email) {
                $signer->forceFill(['email' => $email])->save();
            }

            $link = SignatureRemoteLink::create([
                'signature_signer_id' => $signer->id,
                'token_hash' => $this->hash($token),
                'email' => $email,
                'expires_at' => $vence,
                'sent_by' => $userId,
                'sent_by_name' => $userName,
            ]);

            $link->plainUrl = $this->url($token);

            $mail = new SignatureRemoteLinkMail($link->setRelation('signer', $signer), $link->plainUrl);

            if ($replyTo !== null) {
                $mail->replyTo($replyTo, $userName);
            }

            Mail::to($email)->send($mail);

            $this->states->note($document, SignatureAuditEvent::EVENT_REMOTE_LINK_SENT, [
                'signer' => $signer->id,
                'actor_id' => $userId,
                'payload' => [
                    'link' => $link->id,
                    'email' => $link->maskedEmail(),
                    'vale_ate' => $vence->toDateTimeString(),
                    'prazo_do_documento' => $estendeu ? $vence->toDateTimeString() : $prazoAnterior?->toDateTimeString(),
                    'substituiu' => $substituidos,
                    'enviado_por' => $userName,
                ],
            ]);

            return $link;
        });
    }

    /** Revoga o link: deixa de abrir, e a sessão aberta por ele cai. */
    public function revoke(SignatureRemoteLink $link, ?int $userId = null, ?string $userName = null): SignatureRemoteLink
    {
        if ($link->status !== SignatureRemoteLink::STATUS_ACTIVE) {
            throw new SignatureDocumentLockedException(
                'Este link está ' . mb_strtolower($link->statusLabel()) . ' e não pode ser revogado.'
            );
        }

        return DB::transaction(function () use ($link, $userId, $userName) {
            $this->close($link, SignatureRemoteLink::STATUS_REVOKED, [
                'revoked_at' => now(),
                'revoked_by' => $userId,
                'revoked_by_name' => $userName,
            ], $userId);

            $this->states->note($link->signer->signature_document_id, SignatureAuditEvent::EVENT_REMOTE_LINK_REVOKED, [
                'signer' => $link->signature_signer_id,
                'actor_id' => $userId,
                'payload' => ['link' => $link->id, 'revogado_por' => $userName],
            ]);

            return $link->fresh();
        });
    }

    /**
     * Revoga os links ativos do documento — chamado ao preparar para o
     * gov.br: dali em diante, assina-se só por lá. Devolve quantos eram.
     */
    public function revokeForDocument(SignatureDocument $document, ?int $userId = null, ?string $userName = null): int
    {
        $ativos = SignatureRemoteLink::with('signer')
            ->whereIn('signature_signer_id', $document->signers()->pluck('id'))
            ->where('status', SignatureRemoteLink::STATUS_ACTIVE)
            ->get();

        foreach ($ativos as $link) {
            $this->revoke($link, $userId, $userName);
        }

        return $ativos->count();
    }

    /**
     * O link do token, se ainda abre a tela. Não abre sessão: é o que o site
     * consulta para mostrar a página de entrada.
     *
     * @throws SignatureRemoteLinkException
     */
    public function resolve(?string $token): SignatureRemoteLink
    {
        if (!is_string($token) || preg_match('/^[A-Za-z0-9]{64}$/', $token) !== 1) {
            throw SignatureRemoteLinkException::invalid();
        }

        $link = SignatureRemoteLink::with('signer.document.template')
            ->where('token_hash', $this->hash($token))
            ->first();

        if (!$link || !$link->signer?->document) {
            throw SignatureRemoteLinkException::invalid();
        }

        throw_if($link->status === SignatureRemoteLink::STATUS_SUPERSEDED, SignatureRemoteLinkException::superseded());
        throw_if($link->status === SignatureRemoteLink::STATUS_REVOKED, SignatureRemoteLinkException::revoked());
        throw_if($link->status === SignatureRemoteLink::STATUS_USED, SignatureRemoteLinkException::used());
        throw_if($link->status === SignatureRemoteLink::STATUS_LOCKED, SignatureRemoteLinkException::locked());
        throw_if($link->isExpired(), SignatureRemoteLinkException::expired());

        // Já assinou pelo tablet, documento cancelado, preparado para o gov.br…
        if ($motivo = $link->signer->releaseBlockReason()) {
            throw SignatureRemoteLinkException::unavailable(
                $link->signer->status === SignatureSigner::STATUS_SIGNED
                    ? 'Você já assinou este documento.'
                    : 'Este documento não está mais disponível para assinatura. ' . $motivo,
            );
        }

        return $link;
    }

    /**
     * O que a página de entrada do site mostra antes de começar.
     *
     * Nada de CPF nem de contato: quem tem o link já sabe para quem ele foi.
     *
     * @return array<string, mixed>
     */
    public function summary(SignatureRemoteLink $link): array
    {
        $signer = $link->signer;
        $document = $signer->document;

        return [
            'document' => [
                'title' => $document->title,
                'validation_code' => $document->validation_code,
            ],
            'signer' => [
                'name' => $signer->name,
                'role' => $signer->capacityLabel(),
            ],
            'sent_by' => $link->sent_by_name,
            'sent_at' => $link->created_at?->toIso8601String(),
            'expires_at' => $link->expires_at->toIso8601String(),
            'requirements' => [
                'identity' => 'cpf_full',
                'photo' => true,
                'initials' => $document->requiresInitials(),
                'form' => $document->signingFormPending(),
                // Itens de anexo pedidos (identidade, comprovante); os
                // obrigatórios travam a assinatura no remoto.
                'attachments' => count($document->attachmentRequirements()),
                'attachments_required' => collect($document->attachmentRequirements())->where('required', true)->count(),
            ],
            'identity_attempts_left' => max(0, SignatureCaptureService::MAX_IDENTITY_ATTEMPTS - $link->identity_attempts),
            'server_time' => now()->toIso8601String(),
        ];
    }

    /**
     * Abre a tela de assinatura: emite e consome na hora uma liberação para
     * este link e devolve o segredo da sessão (só o hash dele fica no banco).
     *
     * Uma sessão por link de cada vez: abrir de novo (outra aba, outro
     * aparelho) derruba a anterior.
     *
     * @param  array{ip?: ?string, user_agent?: ?string}  $context
     * @return array{request: SignatureRequest, session_token: string, link: SignatureRemoteLink}
     *
     * @throws SignatureRemoteLinkException
     */
    public function open(?string $token, array $context = []): array
    {
        $link = $this->resolve($token);
        $ip = $context['ip'] ?? null;
        $userAgent = $context['user_agent'] ?? null;

        return DB::transaction(function () use ($link, $ip, $userAgent) {
            foreach ($link->requests()->whereIn('status', [SignatureRequest::STATUS_PENDING, SignatureRequest::STATUS_CONSUMED])->get() as $anterior) {
                $this->requests->cancel($anterior);
            }

            try {
                $emitida = $this->requests->issue(
                    $link->signer,
                    $link->sent_by,
                    $link->sent_by_name,
                    SignatureRequest::IDENTITY_METHOD_REMOTE_LINK,
                    $link->id,
                );
            } catch (SignatureDocumentLockedException $e) {
                throw SignatureRemoteLinkException::unavailable($e->getMessage());
            }

            // As tentativas são do link: a liberação nova começa de onde as
            // anteriores pararam.
            $emitida['request']->forceFill(['identity_attempts' => $link->identity_attempts])->save();

            $sessao = $this->requests->consumeIssued($emitida['request'], $ip, $userAgent, 'link_email');

            $link->forceFill([
                'opened_count' => $link->opened_count + 1,
                'last_opened_at' => now(),
            ])->save();

            /*
             | A data da assinatura dos campos automáticos entra AGORA, antes de
             | a pessoa ver o documento — com a hora do servidor, como na
             | leitura do QR no tablet.
             */
            $this->signingData->prepare($link->signer->document, [
                'signer' => $link->signature_signer_id,
                'request' => $sessao['request']->id,
                'actor_type' => SignatureAuditEvent::ACTOR_KIOSK,
                'ip' => $ip,
                'user_agent' => $userAgent,
            ]);

            return ['request' => $sessao['request'], 'session_token' => $sessao['session_token'], 'link' => $link->fresh()];
        });
    }

    /**
     * A liberação viva de uma sessão remota. Null = sessão vencida, encerrada
     * ou que não é de link remoto (o segredo da sessão do tablet não abre a
     * API remota, nem o contrário).
     */
    public function fromSessionToken(?string $sessionToken): ?SignatureRequest
    {
        $request = $this->requests->fromSessionToken($sessionToken);

        if (!$request?->isRemote()) {
            return null;
        }

        $link = $request->remoteLink;

        return $link && $link->isUsable() ? $request : null;
    }

    /**
     * Confere o CPF — a mesma conferência do tablet, sempre no modo completo —
     * e leva as tentativas para o link. Esgotadas, o link é bloqueado.
     *
     * @param  array{ip?: ?string, user_agent?: ?string}  $context
     *
     * @throws SignatureSessionException
     */
    public function confirmIdentity(SignatureRequest $request, ?string $cpf, array $context = []): SignatureRequest
    {
        try {
            return $this->capture->confirmIdentity($request, $cpf, $context);
        } finally {
            $this->syncAttempts($request->fresh(), $context);
        }
    }

    /**
     * Assina: a captura do tablet, e o link passa a usado.
     *
     * @param  array<string, mixed>  $payload
     * @param  array{ip?: ?string, user_agent?: ?string}  $context
     *
     * @throws SignatureSessionException
     */
    public function sign(SignatureRequest $request, array $payload, array $context = []): SignatureSigner
    {
        /*
         | No balcão o anexo obrigatório segura só a CONCLUSÃO: o atendente está
         | ali e envia o arquivo depois. No remoto não há ninguém para correr
         | atrás — o documento ficaria parado em "Assinado". Por isso aqui ele
         | segura a ASSINATURA (decisão do usuário, 21/10/2026). Quem enviou não
         | importa: um arquivo do atendente também conta.
         */
        $document = $request->signer->document;

        if ($request->signer->status === SignatureSigner::STATUS_PENDING && ($faltam = $document->missingAttachments()) !== []) {
            throw new SignatureRemoteLinkException(
                'Envie antes de assinar: ' . implode(', ', $faltam) . '.',
                409,
                'attachments_missing',
            );
        }

        $signer = $this->capture->capture($request, $payload, $context);

        $this->markUsed($request);

        return $signer;
    }

    /**
     * Recusa pelo link: o documento inteiro vai para recusado, como no
     * tablet, e o link passa a usado.
     *
     * @param  array{ip?: ?string, user_agent?: ?string}  $context
     */
    public function refuse(SignatureRequest $request, ?string $reason, array $context = []): SignatureSigner
    {
        $signer = $this->capture->refuse($request, $reason, $context);

        $this->markUsed($request);

        return $signer;
    }

    /**
     * Os anexos pedidos pelo documento, para a tela do link: cada item com os
     * arquivos já enviados (por quem quer que seja) e se a pessoa pode apagar
     * cada um — só os que ela mesma enviou, antes de assinar.
     *
     * Nada de nome de arquivo nem de quem do clube enviou: só o que a pessoa
     * precisa para saber se o item está atendido.
     *
     * @return array{items: array<int, array<string, mixed>>, missing_required: array<int, string>}
     */
    public function attachments(SignatureRequest $request): array
    {
        $signer = $request->signer;
        $document = $signer->document;
        $anexos = $document->attachments()->get()->groupBy('requirement_key');
        $podeMexer = $signer->status === SignatureSigner::STATUS_PENDING;

        return [
            'items' => array_map(fn(array $item) => [
                'key' => $item['key'],
                'label' => $item['label'],
                'required' => $item['required'],
                'files' => $anexos->get($item['key'], collect())->map(fn($anexo) => [
                    'id' => $anexo->id,
                    'type' => $anexo->extension(),
                    'size_label' => $anexo->sizeLabel(),
                    'sent_at' => $anexo->created_at?->toIso8601String(),
                    'mine' => $anexo->uploaded_by_signer_id === $signer->id,
                    'can_remove' => $podeMexer && $anexo->uploaded_by_signer_id === $signer->id,
                ])->values()->all(),
            ], $document->attachmentRequirements()),
            'missing_required' => $document->missingAttachments(),
            'max_kb' => (int) config('signature.attachments.max_kb', 10240),
            'accepted_types' => ['pdf', 'jpg', 'png'],
        ];
    }

    /** O link montado: `{site_url}/assinar/{token}`. */
    public function url(string $token): string
    {
        return config('signature.remote.site_url') . '/assinar/' . $token;
    }

    /**
     * As tentativas de CPF da liberação viram as do link. Na quinta errada,
     * o link é bloqueado e a sessão cai — abrir de novo não recomeça a conta.
     *
     * @param  array{ip?: ?string, user_agent?: ?string}  $context
     */
    private function syncAttempts(?SignatureRequest $request, array $context): void
    {
        $link = $request?->remoteLink;

        if (!$link || $request->identity_attempts <= $link->identity_attempts) {
            return;
        }

        $link->forceFill(['identity_attempts' => $request->identity_attempts])->save();

        if ($request->identity_confirmed_at !== null
            || $link->identity_attempts < SignatureCaptureService::MAX_IDENTITY_ATTEMPTS
            || $link->status !== SignatureRemoteLink::STATUS_ACTIVE) {
            return;
        }

        DB::transaction(function () use ($link, $context) {
            $this->close($link, SignatureRemoteLink::STATUS_LOCKED);

            $this->states->note($link->signer->signature_document_id, SignatureAuditEvent::EVENT_REMOTE_LINK_LOCKED, [
                'signer' => $link->signature_signer_id,
                'actor_type' => SignatureAuditEvent::ACTOR_KIOSK,
                'ip' => $context['ip'] ?? null,
                'user_agent' => $context['user_agent'] ?? null,
                'payload' => ['link' => $link->id, 'tentativas' => $link->identity_attempts],
            ]);
        });
    }

    private function markUsed(SignatureRequest $request): void
    {
        $link = $request->remoteLink;

        if ($link && $link->status === SignatureRemoteLink::STATUS_ACTIVE) {
            $link->forceFill(['status' => SignatureRemoteLink::STATUS_USED, 'used_at' => now()])->save();
        }
    }

    /**
     * Encerra o link num estado final e derruba a sessão aberta por ele.
     *
     * @param  array<string, mixed>  $extra
     */
    private function close(SignatureRemoteLink $link, string $status, array $extra = [], ?int $userId = null): void
    {
        $link->forceFill(array_merge(['status' => $status], $extra))->save();

        foreach ($link->requests()->whereIn('status', [SignatureRequest::STATUS_PENDING, SignatureRequest::STATUS_CONSUMED])->get() as $aberta) {
            $this->requests->cancel($aberta, $userId);
        }
    }

    /** Marca como substituídos os links ativos do signatário. Devolve quantos eram. */
    private function supersedeActive(SignatureSigner $signer, ?int $userId, ?string $userName): int
    {
        $ativos = SignatureRemoteLink::where('signature_signer_id', $signer->id)
            ->where('status', SignatureRemoteLink::STATUS_ACTIVE)
            ->get();

        foreach ($ativos as $link) {
            $this->close($link, SignatureRemoteLink::STATUS_SUPERSEDED, [], $userId);
        }

        return $ativos->count();
    }

    private function hash(string $value): string
    {
        return hash('sha256', $value);
    }
}
