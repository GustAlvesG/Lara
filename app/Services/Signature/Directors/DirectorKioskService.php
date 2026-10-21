<?php

namespace App\Services\Signature\Directors;

use App\Models\SignatureAuditEvent;
use App\Models\SignatureDocument;
use App\Models\SignatureRequest;
use App\Models\SignatureSigner;
use App\Models\User;
use App\Services\Signature\SignatureRequestService;
use App\Services\Signature\SignatureSigningDataService;
use App\Services\Signature\SignatureStateMachine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * A entrada do DIRETOR no tablet do balcão: matrícula + PIN do Lara, a lista
 * dos documentos pendentes da assinatura dele e a abertura de um deles.
 *
 * Não há QR nem atendente. A entrada abre uma "sessão do diretor" (cookie
 * `lara_director`, só o hash no cache, prazo curto renovado a cada uso), e a
 * escolha de um documento abre a MESMA sessão de assinatura do QR — com a
 * liberação emitida e consumida no servidor, como no Termo de Menores
 * (`consumeIssued`), e a identidade já confirmada pelo PIN. Dali em diante o
 * tablet segue o fluxo de sempre: leitura, aceite, traço, foto.
 *
 * A trava é dupla: limite de tentativas por MATRÍCULA (o tablet inteiro
 * divide um IP, e um throttle por IP trancaria os outros diretores) e, em
 * cada passo, o servidor confere de novo que o usuário está ativo, é do setor
 * Diretoria e é o signatário do documento escolhido.
 */
class DirectorKioskService
{
    public const COOKIE = 'lara_director';

    private const CACHE_PREFIX = 'assinatura-diretoria:sessao:';

    private const LIMIT_PREFIX = 'assinatura-diretoria:login:';

    /** Janela do limite de tentativas, em segundos. */
    private const LIMIT_DECAY_SECONDS = 900;

    public function __construct(
        private SignatureRequestService $requests,
        private SignatureSigningDataService $signingData,
        private SignatureStateMachine $states,
    ) {
    }

    /**
     * Confere matrícula e PIN e abre a sessão do diretor. Devolve o usuário e
     * o valor do cookie (em claro só aqui).
     *
     * Uma resposta só para "não existe", "inativo", "sem PIN" e "PIN errado":
     * separar diria a quem tenta qual matrícula existe. "Não é diretor" vem
     * depois do PIN certo — aí a pessoa já provou quem é.
     *
     * @return array{user: User, token: string}
     *
     * @throws DirectorException
     */
    public function login(string $matricula, string $pin, ?string $ip = null): array
    {
        $matricula = trim($matricula);
        $chave = self::LIMIT_PREFIX . Str::lower($matricula);
        $maximo = max(1, (int) config('signature.directors.max_login_attempts', 5));

        if (RateLimiter::tooManyAttempts($chave, $maximo)) {
            throw new DirectorException(
                'Muitas tentativas para esta matrícula. Tente de novo em '
                    . (int) ceil(RateLimiter::availableIn($chave) / 60) . ' minuto(s).',
                429,
            );
        }

        $user = $matricula !== '' ? $this->findByMatricula($matricula) : null;

        if (!$user || (int) $user->status_id !== 1 || !$user->checkPin($pin)) {
            RateLimiter::hit($chave, self::LIMIT_DECAY_SECONDS);

            Log::warning('Assinatura Diretoria: entrada recusada no tablet.', ['matricula' => $matricula, 'ip' => $ip]);

            throw new DirectorException('Matrícula ou PIN inválido.', 401);
        }

        RateLimiter::clear($chave);

        if (!$user->isDirector()) {
            throw new DirectorException('Seu usuário não é do setor ' . User::DIRECTORS_SECTOR
                . ': a assinatura da diretoria não está disponível para você.', 403);
        }

        $token = Str::random(64);

        Cache::put(self::CACHE_PREFIX . hash('sha256', $token), $user->id, $this->sessionTtl());

        Log::info('Assinatura Diretoria: diretor entrou no tablet.', ['user_id' => $user->id, 'ip' => $ip]);

        return ['user' => $user, 'token' => $token];
    }

    /**
     * O diretor da sessão, a partir do cookie — ou null (sem cookie, vencida,
     * usuário que deixou de ser ativo ou de ser da Diretoria). Cada consulta
     * renova o prazo.
     */
    public function fromToken(?string $token): ?User
    {
        if (!$token) {
            return null;
        }

        $chave = self::CACHE_PREFIX . hash('sha256', $token);
        $userId = Cache::get($chave);

        if (!$userId) {
            return null;
        }

        $user = User::find($userId);

        if (!$user || (int) $user->status_id !== 1 || !$user->isDirector()) {
            Cache::forget($chave);

            return null;
        }

        Cache::put($chave, $user->id, $this->sessionTtl());

        return $user;
    }

    public function logout(?string $token): void
    {
        if ($token) {
            Cache::forget(self::CACHE_PREFIX . hash('sha256', $token));
        }
    }

    /**
     * Os signatários pendentes deste diretor que o tablet pode abrir: documento
     * aguardando assinatura, dentro do prazo e que não foi para o gov.br (lá a
     * assinatura é pelo arquivo, não pelo tablet).
     *
     * @return Collection<int, SignatureSigner>
     */
    public function pending(User $director): Collection
    {
        return SignatureSigner::with(['document.signers'])
            ->where('user_id', $director->id)
            ->whereNotNull('director_area_id')
            ->where('status', SignatureSigner::STATUS_PENDING)
            ->whereHas('document', fn($q) => $q
                ->where('status', SignatureDocument::STATUS_AWAITING_SIGNATURE)
                ->whereNull('govbr_sent_at')
                ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now())))
            ->orderBy('id')
            ->get();
    }

    /**
     * A lista como vai ao tablet: o necessário para o diretor reconhecer o
     * documento, e nada dos outros signatários além do nome e se já assinou.
     *
     * @return array<int, array<string, mixed>>
     */
    public function pendingPayload(User $director): array
    {
        return $this->pending($director)->map(fn(SignatureSigner $s) => [
            'signer_id' => $s->id,
            'title' => $s->document->title,
            'area' => $s->directorLabel(),
            'created_by' => $s->document->created_by_name,
            'frozen_at' => $s->document->frozen_at?->format('d/m/Y H:i'),
            'expires_at' => $s->document->expires_at?->format('d/m/Y H:i'),
            'others' => $s->document->signers
                ->reject(fn(SignatureSigner $o) => $o->id === $s->id)
                ->map(fn(SignatureSigner $o) => [
                    'name' => $o->name,
                    'signed' => $o->status === SignatureSigner::STATUS_SIGNED,
                ])
                ->values()
                ->all(),
        ])->values()->all();
    }

    /**
     * Abre a assinatura de um documento da lista. Devolve a liberação já
     * consumida e o valor do cookie `lara_sign`.
     *
     * @param  array{ip?: ?string, user_agent?: ?string}  $context
     * @return array{request: SignatureRequest, session_token: string}
     *
     * @throws DirectorException
     */
    public function open(User $director, int $signerId, array $context = []): array
    {
        $signer = $this->pending($director)->firstWhere('id', $signerId);

        if (!$signer) {
            throw new DirectorException('Este documento não está mais pendente da sua assinatura. Atualize a lista.', 409);
        }

        $ip = $context['ip'] ?? null;
        $userAgent = $context['user_agent'] ?? null;

        try {
            $liberacao = $this->requests->issue(
                $signer,
                $director->id,
                $director->name . ' (entrada do diretor no tablet)',
                SignatureRequest::IDENTITY_METHOD_DIRECTOR_PIN,
            );

            $sessao = $this->requests->consumeIssued($liberacao['request'], $ip, $userAgent, 'diretoria_matricula_pin');
        } catch (\App\Exceptions\SignatureDocumentLockedException|\App\Exceptions\SignatureSessionException $e) {
            throw new DirectorException($e->getMessage(), 409);
        }

        $solicitacao = $sessao['request'];

        $solicitacao->forceFill(['identity_confirmed_at' => now()])->save();

        $contexto = [
            'signer' => $signer->id,
            'request' => $solicitacao->id,
            'actor_type' => SignatureAuditEvent::ACTOR_KIOSK,
            'ip' => $ip,
            'user_agent' => $userAgent,
        ];

        $this->states->note($signer->signature_document_id, SignatureAuditEvent::EVENT_IDENTITY_CONFIRMED, $contexto + [
            // A matrícula e não o nome: é ela que o diretor digitou.
            'payload' => ['modo' => SignatureRequest::IDENTITY_METHOD_DIRECTOR_PIN, 'matricula' => $director->matricula],
        ]);

        // A data da assinatura entra antes da leitura, como no QR do balcão.
        $this->signingData->prepare($signer->document()->first(), $contexto);

        return ['request' => $solicitacao->fresh(), 'session_token' => $sessao['session_token']];
    }

    /** Inatividade na lista do diretor, no tablet, que encerra a entrada dele. */
    public function idleSeconds(): int
    {
        return max(15, (int) config('signature.directors.idle_seconds', 90));
    }

    public function sessionMinutes(): int
    {
        return max(1, (int) config('signature.directors.session_minutes', 10));
    }

    private function sessionTtl(): \DateTimeInterface
    {
        return now()->addMinutes($this->sessionMinutes());
    }

    /** Costura para os testes: a busca do usuário pela matrícula. */
    protected function findByMatricula(string $matricula): ?User
    {
        return User::where('matricula', $matricula)->first();
    }
}
