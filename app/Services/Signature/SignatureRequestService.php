<?php

namespace App\Services\Signature;

use App\Exceptions\SignatureDocumentLockedException;
use App\Exceptions\SignatureSessionException;
use App\Models\SignatureAuditEvent;
use App\Models\SignatureDocument;
use App\Models\SignatureRequest;
use App\Models\SignatureSigner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A liberação de UM documento para o tablet, por QR Code — e a sessão que
 * nasce da leitura dele.
 *
 * O desenho inteiro existe para que o tablet não guarde nada. Ele fica numa
 * tela de espera, sem token, sem saber qual é o próximo documento; o atendente
 * gera um QR por signatário, o tablet lê, e aquela leitura vale por um
 * atendimento só. Terminado o fluxo, a sessão morre e ele volta à espera.
 *
 * Três decisões sustentam isso:
 *
 *  - o token tem 64 caracteres aleatórios e o banco guarda só o sha256 — quem
 *    lê o banco não abre sessão nenhuma;
 *  - a primeira leitura CONSOME o token; a segunda é recusada e auditada,
 *    esteja ela no mesmo aparelho ou em outro;
 *  - o QR expira em minutos, e a sessão que nasce dele tem prazo próprio.
 *
 * Regerar um QR marca o anterior como `superseded`, e não como `canceled`: a
 * diferença aparece na auditoria e explica, meses depois, por que existem três
 * QRs para o mesmo signatário.
 */
class SignatureRequestService
{
    /** Tamanho do token em claro. 64 caracteres alfanuméricos. */
    private const TOKEN_LENGTH = 64;

    /**
     * Alfabeto do código digitado: sem 0/O e 1/I/L, que ninguém dita por cima
     * do balcão sem alguém errar. É o mesmo do código de validação pública.
     */
    private const MANUAL_ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    /**
     * 8 caracteres, exibidos em dois blocos de 4.
     *
     * 31^8 é da ordem de 8·10^11. Não é o token de 64 caracteres, e não
     * precisa ser: o código vive dois minutos e meio, vale uma leitura só, e a
     * rota de consumo aceita 10 tentativas por minuto. Para varrer 1% desse
     * espaço nesse ritmo seriam necessários uns quinze mil anos.
     */
    private const MANUAL_CODE_LENGTH = 8;

    public function __construct(private SignatureStateMachine $states)
    {
    }

    /**
     * Gera a liberação e devolve o token EM CLARO, que existe só nesta
     * resposta: é dele que a tela do atendente desenha o QR, e ele não é
     * gravado em lugar nenhum.
     *
     * @return array{request: SignatureRequest, token: string, payload: string}
     *
     * @throws SignatureDocumentLockedException
     */
    public function issue(SignatureSigner $signer, ?int $userId = null): array
    {
        if ($motivo = $signer->releaseBlockReason()) {
            throw new SignatureDocumentLockedException($motivo);
        }

        return DB::transaction(function () use ($signer, $userId) {
            $anteriores = $this->supersedePending($signer, $userId);

            // Str::random usa random_bytes — aleatoriedade criptográfica, e não
            // o mt_rand que um `rand()` daria.
            $token = Str::random(self::TOKEN_LENGTH);

            // O código digitado só existe quando o modo sem HTTPS está ligado.
            // Desligado, a coluna fica nula e não há segredo curto nenhum para
            // ser adivinhado — ver config/signature.php.
            $manualCode = $this->manualCodeEnabled() ? $this->generateManualCode() : null;

            $request = SignatureRequest::create([
                'signature_signer_id' => $signer->id,
                'token_hash' => $this->hash($token),
                'manual_code_hash' => $manualCode === null ? null : $this->hash($manualCode),
                'expires_at' => now()->addSeconds((int) config('signature.qr_ttl_seconds', 300)),
                'created_by' => $userId,
            ]);

            $this->states->note(
                $signer->signature_document_id,
                $anteriores > 0 ? SignatureAuditEvent::EVENT_QR_REISSUED : SignatureAuditEvent::EVENT_QR_ISSUED,
                [
                    'signer' => $signer->id,
                    'request' => $request->id,
                    'actor_id' => $userId,
                    'payload' => [
                        'expira_em_segundos' => (int) config('signature.qr_ttl_seconds', 300),
                        'substituiu' => $anteriores,
                    ],
                ],
            );

            return [
                'request' => $request,
                'token' => $token,
                'payload' => SignatureRequest::qrPayload($token),
                // Em claro só aqui, como o token: é o que o atendente lê em voz
                // alta, e não é gravado em lugar nenhum.
                'manual_code' => $manualCode,
                'manual_code_expires_in' => $manualCode === null
                    ? null
                    : (int) config('signature.manual_code.ttl_seconds', 150),
            ];
        });
    }

    /**
     * Consome o token lido pelo tablet e abre a sessão.
     *
     * Devolve o valor do cookie de sessão — o segundo segredo do desenho. O
     * banco guarda só o hash dele, do mesmo jeito que o do token: um cookie
     * roubado do banco não existe.
     *
     * @return array{request: SignatureRequest, session_token: string}
     *
     * @throws SignatureSessionException
     */
    public function consume(string $token, ?string $ip = null, ?string $userAgent = null): array
    {
        $request = SignatureRequest::where('token_hash', $this->hash($token))->first();

        if (!$request) {
            throw SignatureSessionException::invalidToken();
        }

        return $this->open($request, $ip, $userAgent, manual: false);
    }

    /**
     * Consome o CÓDIGO DIGITADO — o caminho de quem não tem câmera.
     *
     * Mesma liberação, mesmas travas: uso único, vínculo com um documento,
     * faixa de IP. Duas diferenças, e ambas apertam:
     *
     *  - o prazo é mais curto (`manual_code.ttl_seconds`), porque um código
     *    ditado em voz alta no balcão é ouvido por quem está na fila;
     *  - só existe com o modo ligado na configuração. Desligado, esta rota
     *    responde como se o código não existisse — que é a verdade.
     *
     * @return array{request: SignatureRequest, session_token: string}
     *
     * @throws SignatureSessionException
     */
    public function consumeManualCode(string $code, ?string $ip = null, ?string $userAgent = null): array
    {
        if (!$this->manualCodeEnabled()) {
            throw SignatureSessionException::invalidToken();
        }

        $normalizado = $this->normalizeManualCode($code);

        if ($normalizado === '') {
            throw SignatureSessionException::invalidToken();
        }

        $request = SignatureRequest::where('manual_code_hash', $this->hash($normalizado))->first();

        if (!$request) {
            throw SignatureSessionException::invalidToken();
        }

        /*
         | Prazo próprio, contado da EMISSÃO. Vence antes do QR, então o código
         | pode estar morto com a liberação ainda válida — e aí o atendente
         | gera outro, que é o comportamento desejado.
         */
        $limite = $request->created_at?->copy()
            ->addSeconds((int) config('signature.manual_code.ttl_seconds', 150));

        if ($request->status === SignatureRequest::STATUS_PENDING && $limite?->isPast()) {
            throw SignatureSessionException::expired();
        }

        return $this->open($request, $ip, $userAgent, manual: true);
    }

    /**
     * As travas comuns aos dois caminhos e a abertura da sessão.
     *
     * Existe para que QR e código digitado NÃO tenham duas listas de
     * verificação: o dia em que elas divergirem é o dia em que uma delas deixa
     * passar o que a outra barra.
     *
     * @return array{request: SignatureRequest, session_token: string}
     *
     * @throws SignatureSessionException
     */
    private function open(
        SignatureRequest $request,
        ?string $ip,
        ?string $userAgent,
        bool $manual,
    ): array {
        $signer = $request->signer;
        $documentId = $signer?->signature_document_id;

        /*
         | Releitura: o QR foi fotografado, ou o mesmo tablet leu duas vezes.
         | É o caso que mais interessa registrar — e o único em que a recusa
         | vira evento próprio na auditoria.
         */
        if ($request->status === SignatureRequest::STATUS_CONSUMED
            || $request->status === SignatureRequest::STATUS_COMPLETED) {
            $this->states->note($documentId, SignatureAuditEvent::EVENT_QR_REUSE_BLOCKED, [
                'signer' => $signer?->id,
                'request' => $request->id,
                'actor_type' => SignatureAuditEvent::ACTOR_KIOSK,
                'ip' => $ip,
                'user_agent' => $userAgent,
                'payload' => ['consumido_em' => $request->consumed_at?->toDateTimeString()],
            ]);

            throw SignatureSessionException::alreadyUsed();
        }

        if ($request->status === SignatureRequest::STATUS_SUPERSEDED) {
            throw SignatureSessionException::superseded();
        }

        if ($request->status === SignatureRequest::STATUS_CANCELED) {
            throw SignatureSessionException::canceled();
        }

        if (!$request->isReadable()) {
            // Expirado por tempo: marca o estado antes de recusar, para a tela
            // do atendente parar de mostrar um QR que não vale mais.
            if ($request->status === SignatureRequest::STATUS_PENDING) {
                $this->states->requestTo(
                    $request,
                    SignatureRequest::STATUS_EXPIRED,
                    SignatureAuditEvent::EVENT_EXPIRED,
                    [],
                    ['actor_type' => SignatureAuditEvent::ACTOR_SYSTEM],
                );
            }

            throw SignatureSessionException::expired();
        }

        if (!$this->ipIsAllowed($ip)) {
            $this->states->note($documentId, SignatureAuditEvent::EVENT_QR_IP_BLOCKED, [
                'signer' => $signer?->id,
                'request' => $request->id,
                'actor_type' => SignatureAuditEvent::ACTOR_KIOSK,
                'ip' => $ip,
                'user_agent' => $userAgent,
            ]);

            throw SignatureSessionException::outsideNetwork();
        }

        // O documento pode ter sido cancelado entre a geração do QR e a
        // leitura — a tela do atendente e o tablet são dois aparelhos.
        if ($motivo = $signer?->releaseBlockReason()) {
            throw new SignatureSessionException($motivo, 409);
        }

        $sessionToken = Str::random(self::TOKEN_LENGTH);

        $this->states->requestTo(
            $request,
            SignatureRequest::STATUS_CONSUMED,
            SignatureAuditEvent::EVENT_QR_CONSUMED,
            [
                'consumed_at' => now(),
                'consumed_ip' => $ip,
                'consumed_user_agent' => $userAgent === null ? null : mb_substr($userAgent, 0, 255),
                'session_hash' => $this->hash($sessionToken),
                'session_expires_at' => now()->addMinutes((int) config('signature.session_ttl_minutes', 15)),
            ],
            [
                'actor_type' => SignatureAuditEvent::ACTOR_KIOSK,
                'ip' => $ip,
                'user_agent' => $userAgent,
                // Como o segredo chegou ao tablet fica na trilha: meses depois,
                // é o que explica um atendimento sem foto.
                'payload' => ['via' => $manual ? 'codigo_digitado' : 'qr_code'],
            ],
        );

        return ['request' => $request->fresh(), 'session_token' => $sessionToken];
    }

    /**
     * A solicitação viva de uma sessão do tablet, a partir do valor do cookie.
     *
     * Devolve null para cookie ausente, desconhecido, de sessão vencida ou de
     * atendimento que já acabou. O middleware transforma isso em 419 — nunca
     * em "não existe", que ensinaria a diferença entre um cookie forjado e um
     * cookie velho.
     */
    public function fromSessionToken(?string $sessionToken): ?SignatureRequest
    {
        if (!$sessionToken) {
            return null;
        }

        $request = SignatureRequest::with('signer.document')
            ->where('session_hash', $this->hash($sessionToken))
            ->first();

        return $request?->sessionIsAlive() ? $request : null;
    }

    /** Encerra a sessão do tablet depois de assinar ou recusar. */
    public function complete(SignatureRequest $request, array $context = []): SignatureRequest
    {
        return $this->states->requestTo(
            $request,
            SignatureRequest::STATUS_COMPLETED,
            SignatureAuditEvent::EVENT_QR_CONSUMED,
            ['session_expires_at' => now()],
            array_merge(['actor_type' => SignatureAuditEvent::ACTOR_KIOSK], $context),
        );
    }

    /**
     * Cancela a liberação. Serve tanto ao QR ainda não lido quanto à sessão já
     * aberta: no segundo caso, o tablet cai na requisição seguinte.
     */
    public function cancel(SignatureRequest $request, ?int $userId = null): SignatureRequest
    {
        if (!$this->states->canRequestGoTo($request->status, SignatureRequest::STATUS_CANCELED)) {
            throw new SignatureSessionException(
                'Esta liberação está ' . mb_strtolower($request->statusLabel()) . ' e não pode ser cancelada.',
                409,
            );
        }

        return $this->states->requestTo(
            $request,
            SignatureRequest::STATUS_CANCELED,
            SignatureAuditEvent::EVENT_CANCELED,
            ['session_expires_at' => now()],
            ['actor_id' => $userId],
        );
    }

    /**
     * Expira o que passou do prazo — chamado pelo comando agendado.
     *
     * São dois prazos distintos: o QR que ninguém leu (`expires_at`) e a
     * sessão aberta que ficou parada (`session_expires_at`). O segundo é o
     * caso da pessoa que desistiu e foi embora com o tablet na tela.
     *
     * @return array{requests: int, documents: int}
     */
    public function expireDue(): array
    {
        $solicitacoes = 0;

        $vencidas = SignatureRequest::with('signer')
            ->where(function ($query) {
                $query->where('status', SignatureRequest::STATUS_PENDING)
                    ->where('expires_at', '<=', now());
            })
            ->orWhere(function ($query) {
                $query->where('status', SignatureRequest::STATUS_CONSUMED)
                    ->whereNotNull('session_expires_at')
                    ->where('session_expires_at', '<=', now());
            })
            ->get();

        foreach ($vencidas as $request) {
            $this->states->requestTo(
                $request,
                SignatureRequest::STATUS_EXPIRED,
                SignatureAuditEvent::EVENT_EXPIRED,
                [],
                ['actor_type' => SignatureAuditEvent::ACTOR_SYSTEM],
            );

            $solicitacoes++;
        }

        $documentos = 0;

        $esquecidos = SignatureDocument::with('signers')
            ->where('status', SignatureDocument::STATUS_AWAITING_SIGNATURE)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        foreach ($esquecidos as $document) {
            DB::transaction(function () use ($document) {
                foreach ($document->signers->where('status', SignatureSigner::STATUS_PENDING) as $signer) {
                    $this->states->signerTo(
                        $signer,
                        SignatureSigner::STATUS_EXPIRED,
                        SignatureAuditEvent::EVENT_EXPIRED,
                        [],
                        ['actor_type' => SignatureAuditEvent::ACTOR_SYSTEM],
                    );
                }

                $this->states->documentTo(
                    $document,
                    SignatureDocument::STATUS_EXPIRED,
                    SignatureAuditEvent::EVENT_EXPIRED,
                    [],
                    ['actor_type' => SignatureAuditEvent::ACTOR_SYSTEM],
                );
            });

            $documentos++;
        }

        return ['requests' => $solicitacoes, 'documents' => $documentos];
    }

    /**
     * Marca como substituídas as liberações pendentes do signatário. Devolve
     * quantas eram.
     */
    private function supersedePending(SignatureSigner $signer, ?int $userId): int
    {
        $pendentes = SignatureRequest::where('signature_signer_id', $signer->id)
            ->where('status', SignatureRequest::STATUS_PENDING)
            ->get();

        foreach ($pendentes as $pendente) {
            $this->states->requestTo(
                $pendente,
                SignatureRequest::STATUS_SUPERSEDED,
                SignatureAuditEvent::EVENT_QR_REISSUED,
                [],
                ['actor_id' => $userId],
            );
        }

        return $pendentes->count();
    }

    private function hash(string $value): string
    {
        return hash('sha256', $value);
    }

    public function manualCodeEnabled(): bool
    {
        return (bool) config('signature.manual_code.enabled', false);
    }

    /**
     * Sorteia um código curto que não colida com nenhum outro AINDA VIVO.
     *
     * Colisão com um código já expirado ou consumido não importa: aquele não
     * abre mais nada. O que não pode haver é dois códigos válidos iguais
     * apontando para liberações diferentes.
     */
    private function generateManualCode(): string
    {
        do {
            $codigo = '';

            for ($i = 0; $i < self::MANUAL_CODE_LENGTH; $i++) {
                $codigo .= self::MANUAL_ALPHABET[random_int(0, strlen(self::MANUAL_ALPHABET) - 1)];
            }

            $vivo = SignatureRequest::where('manual_code_hash', $this->hash($codigo))
                ->where('status', SignatureRequest::STATUS_PENDING)
                ->exists();
        } while ($vivo);

        return $codigo;
    }

    /**
     * Normaliza o que a pessoa digitou: maiúsculas, sem espaço nem hífen.
     *
     * O código é exibido em dois blocos ("A7K2 9MPX") e alguém vai digitar o
     * espaço. Recusar por causa disso seria transformar a apresentação em
     * regra.
     */
    private function normalizeManualCode(string $code): string
    {
        $limpo = mb_strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');

        return strlen($limpo) === self::MANUAL_CODE_LENGTH ? $limpo : '';
    }

    /**
     * Faixa de IP permitida para CONSUMIR um token, quando configurada.
     *
     * Trava a MAIS, não a principal: quem protege é o token de uso único. Esta
     * cobre o caso de alguém fotografar o QR e tentar abri-lo de fora da rede
     * do clube. Lista vazia = sem restrição.
     */
    private function ipIsAllowed(?string $ip): bool
    {
        $faixas = (array) config('signature.allowed_ips', []);

        if ($faixas === []) {
            return true;
        }

        if ($ip === null) {
            return false;
        }

        foreach ($faixas as $faixa) {
            if ($this->ipMatches($ip, (string) $faixa)) {
                return true;
            }
        }

        return false;
    }

    /** IP solto ou CIDR (IPv4). */
    private function ipMatches(string $ip, string $range): bool
    {
        if (!str_contains($range, '/')) {
            return $ip === $range;
        }

        [$rede, $bits] = explode('/', $range, 2);

        $ipLong = ip2long($ip);
        $redeLong = ip2long($rede);

        if ($ipLong === false || $redeLong === false) {
            return false;
        }

        $bits = (int) $bits;

        if ($bits < 0 || $bits > 32) {
            return false;
        }

        // /0 pega tudo; o shift de 32 é indefinido em PHP, por isso o caso à parte.
        $mascara = $bits === 0 ? 0 : -1 << (32 - $bits);

        return ($ipLong & $mascara) === ($redeLong & $mascara);
    }
}
