<?php

namespace App\Services\Signature\Directors;

use App\Models\SignatureAuditEvent;
use App\Models\SignatureDocument;
use App\Models\SignatureRequest;
use App\Models\SignatureSigner;
use App\Models\User;
use App\Exceptions\SignatureSessionException;
use App\Services\Signature\SignatureCaptureService;
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

    /** Quantos documentos cabem numa assinatura em lote. */
    public const BATCH_MAX = 20;

    /** "Já assinados" no tablet: os deste período, no máximo SIGNED_LIMIT. */
    private const SIGNED_DAYS = 90;

    private const SIGNED_LIMIT = 50;

    public function __construct(
        private SignatureRequestService $requests,
        private SignatureSigningDataService $signingData,
        private SignatureStateMachine $states,
        private SignatureCaptureService $capture,
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
     * aguardando assinatura, dentro do prazo, que não foi para o gov.br (lá a
     * assinatura é pelo arquivo, não pelo tablet) e que já CHEGOU à diretoria —
     * ninguém de fora dela falta assinar (decisão do usuário, 26/10/2026).
     *
     * @return Collection<int, SignatureSigner>
     */
    public function pending(User $director): Collection
    {
        return SignatureSigner::with(['document.signers', 'document.template'])
            ->where('user_id', $director->id)
            ->whereNotNull('director_area_id')
            ->where('status', SignatureSigner::STATUS_PENDING)
            ->whereHas('document', fn($q) => $q
                ->where('status', SignatureDocument::STATUS_AWAITING_SIGNATURE)
                ->whereNull('govbr_sent_at')
                ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->releasedToDirectors())
            ->orderBy('id')
            ->get();
    }

    /**
     * Entra na assinatura em lote? Só o documento sem perguntas a responder:
     * as respostas refazem o texto, e cada um teria o seu formulário. O visto
     * não impede — é um desenho só, aplicado a quem o exige.
     */
    public function batchable(SignatureSigner $signer): bool
    {
        return $this->signingData->form($signer->document) === null;
    }

    /**
     * Os documentos que este diretor já assinou, para acompanhar: o que falta
     * e se a via final já saiu. Só leitura, sem PDF — o tablet é do balcão.
     *
     * @return array<int, array<string, mixed>>
     */
    public function signedPayload(User $director): array
    {
        return SignatureSigner::with(['document.signers'])
            ->where('user_id', $director->id)
            ->whereNotNull('director_area_id')
            ->where('status', SignatureSigner::STATUS_SIGNED)
            ->where('signed_at', '>=', now()->subDays(self::SIGNED_DAYS))
            ->orderByDesc('signed_at')
            ->limit(self::SIGNED_LIMIT)
            ->get()
            ->map(fn(SignatureSigner $s) => [
                'signer_id' => $s->id,
                'title' => $s->document->title,
                'area' => $s->directorLabel(),
                'signed_at' => $s->signed_at?->format('d/m/Y H:i'),
                'situation' => $this->situation($s->document),
                'done' => $s->document->status === SignatureDocument::STATUS_FINALIZED,
            ])
            ->values()
            ->all();
    }

    /** Em que pé está o documento, na voz do tablet. */
    private function situation(SignatureDocument $document): string
    {
        return match ($document->status) {
            SignatureDocument::STATUS_FINALIZED => 'Concluído: a via final foi emitida.',
            SignatureDocument::STATUS_SIGNED => 'Todos assinaram: a via final está sendo gerada.',
            SignatureDocument::STATUS_AWAITING_SIGNATURE => 'Falta assinar: ' . $document->signers
                ->where('status', SignatureSigner::STATUS_PENDING)
                ->pluck('name')
                ->join(', ', ' e ') . '.',
            SignatureDocument::STATUS_REFUSED => 'Recusado por outro signatário.',
            default => $document->statusLabel() . '.',
        };
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
            // Pode ir na assinatura em lote (sem perguntas a responder).
            'batch' => $this->batchable($s),
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

    /*
     | Assinatura em lote (26/10/2026)
     |
     | O diretor marca vários documentos da lista, lê um por um e assina UMA
     | vez: aceite, traço, visto (se algum pedir) e foto valem para todos. Por
     | baixo, cada documento é aberto como na assinatura avulsa — liberação
     | emitida e consumida, identidade confirmada pelo PIN, data aplicada —, e
     | cada um recebe a sua leitura e a sua assinatura pela MESMA captura do
     | tablet, com a trilha marcando `em_lote`. Nada fica no cache: a liberação
     | viva de cada documento é achada no banco (diretor, PIN, consumida, no
     | prazo), e só a entrada do diretor chega até ela.
     */

    /**
     * Abre o lote. Tudo ou nada: se um documento não abrir, os já abertos são
     * cancelados e o lote é recusado com o motivo.
     *
     * @param  array<int, int>  $signerIds
     * @param  array{ip?: ?string, user_agent?: ?string}  $context
     * @return array{documents: array<int, array<string, mixed>>, requires_initials: bool, remaining_seconds: int}
     *
     * @throws DirectorException
     */
    public function openBatch(User $director, array $signerIds, array $context = []): array
    {
        $ids = array_values(array_unique(array_map('intval', $signerIds)));

        if ($ids === [] || count($ids) > self::BATCH_MAX) {
            throw new DirectorException('Escolha de 1 a ' . self::BATCH_MAX . ' documentos para assinar de uma vez.', 422);
        }

        $pendentes = $this->pending($director)->keyBy('id');

        foreach ($ids as $id) {
            $signer = $pendentes->get($id);

            if (!$signer) {
                throw new DirectorException('Um dos documentos escolhidos não está mais pendente da sua assinatura. Atualize a lista.', 409);
            }

            if (!$this->batchable($signer)) {
                throw new DirectorException('"' . $signer->document->title . '" tem perguntas para responder: assine esse sozinho.', 422);
            }
        }

        $abertos = [];

        try {
            foreach ($ids as $id) {
                $abertos[$id] = $this->open($director, $id, $context)['request'];
            }
        } catch (DirectorException $e) {
            foreach ($abertos as $solicitacao) {
                $this->cancelQuietly($solicitacao);
            }

            throw $e;
        }

        $documentos = collect($abertos)->map(function (SignatureRequest $r, int $id) {
            $documento = $r->signer->document()->first();

            return [
                'signer_id' => $id,
                'title' => $documento->title,
                'area' => $r->signer->directorLabel(),
                // O hash muda se a data automática refez o arquivo.
                'pdf_url' => route('quiosque.diretoria.batch.pdf', $id) . '?v=' . substr((string) $documento->original_sha256, 0, 12),
                'requires_initials' => $documento->requiresInitials(),
            ];
        })->values()->all();

        return [
            'documents' => $documentos,
            'requires_initials' => collect($documentos)->contains('requires_initials', true),
            'remaining_seconds' => collect($abertos)->min(fn(SignatureRequest $r) => $r->secondsToSessionEnd()),
        ];
    }

    /**
     * A liberação viva de um documento do lote: aberta pela entrada DESTE
     * diretor, consumida e no prazo — ou null.
     */
    public function batchRequest(User $director, int $signerId): ?SignatureRequest
    {
        $solicitacao = SignatureRequest::with('signer.document')
            ->where('signature_signer_id', $signerId)
            ->where('identity_method', SignatureRequest::IDENTITY_METHOD_DIRECTOR_PIN)
            ->where('created_by', $director->id)
            ->where('status', SignatureRequest::STATUS_CONSUMED)
            ->orderByDesc('id')
            ->first();

        if (!$solicitacao || !$solicitacao->sessionIsAlive()
            || (int) $solicitacao->signer?->user_id !== (int) $director->id
            || $solicitacao->signer->releaseBlockReason(true)) {
            return null;
        }

        return $solicitacao;
    }

    /**
     * Registra a leitura de um documento do lote — o mesmo evento da leitura
     * avulsa, com a marca do lote.
     *
     * @param  array{ip?: ?string, user_agent?: ?string}  $context
     *
     * @throws DirectorException
     */
    public function batchViewed(User $director, int $signerId, ?int $seconds, bool $scrolledToEnd, array $context = []): void
    {
        $solicitacao = $this->batchRequest($director, $signerId)
            ?? throw new DirectorException('A sessão deste documento terminou. Volte à lista e abra de novo.', 409);

        $this->states->note($solicitacao->signer->signature_document_id, SignatureAuditEvent::EVENT_VIEWED, [
            'signer' => $signerId,
            'request' => $solicitacao->id,
            'actor_type' => SignatureAuditEvent::ACTOR_KIOSK,
            'ip' => $context['ip'] ?? null,
            'user_agent' => $context['user_agent'] ?? null,
            'payload' => [
                'segundos_de_leitura' => $seconds,
                'rolou_ate_o_fim' => $scrolledToEnd,
                'em_lote' => true,
            ],
        ]);
    }

    /**
     * Assina o lote: a mesma captura do tablet, documento a documento. Um que
     * falhe não desfaz os outros — cada assinatura é um ato completo —, e o
     * resultado diz qual entrou e qual não.
     *
     * @param  array<int, int>  $signerIds
     * @param  array<string, mixed>  $payload  o mesmo da captura, sem leitura
     * @param  array<int|string, array{read_seconds?: ?int, scrolled_to_end?: bool}>  $reads  por signatário
     * @param  array{ip?: ?string, user_agent?: ?string}  $context
     * @return array<int, array{signer_id: int, title: ?string, ok: bool, error: ?string}>
     */
    public function signBatch(User $director, array $signerIds, array $payload, array $reads, array $context = []): array
    {
        $ids = array_values(array_unique(array_map('intval', $signerIds)));
        $resultado = [];

        foreach ($ids as $id) {
            $solicitacao = $this->batchRequest($director, $id);

            if (!$solicitacao) {
                $resultado[] = ['signer_id' => $id, 'title' => null, 'ok' => false,
                    'error' => 'A sessão deste documento terminou antes da assinatura. Abra de novo na lista.'];

                continue;
            }

            $leitura = $reads[$id] ?? $reads[(string) $id] ?? [];

            try {
                $this->capture->capture($solicitacao, array_merge($payload, [
                    'read_seconds' => $leitura['read_seconds'] ?? null,
                    'scrolled_to_end' => (bool) ($leitura['scrolled_to_end'] ?? false),
                    'batch_size' => count($ids),
                    // A via por e-mail é do signatário avulso; no lote, não.
                    'wants_copy' => false,
                ]), $context);

                $resultado[] = ['signer_id' => $id, 'title' => $solicitacao->signer->document->title, 'ok' => true, 'error' => null];
            } catch (SignatureSessionException $e) {
                $resultado[] = ['signer_id' => $id, 'title' => $solicitacao->signer->document->title, 'ok' => false, 'error' => $e->getMessage()];
            }
        }

        return $resultado;
    }

    /**
     * Desistiu do lote (voltou à lista): as liberações abertas caem, como no
     * "Voltar" da assinatura avulsa.
     *
     * @param  array<int, int>  $signerIds
     */
    public function cancelBatch(User $director, array $signerIds): void
    {
        foreach (array_unique(array_map('intval', $signerIds)) as $id) {
            if ($solicitacao = $this->batchRequest($director, $id)) {
                $this->cancelQuietly($solicitacao);
            }
        }
    }

    private function cancelQuietly(SignatureRequest $solicitacao): void
    {
        try {
            $this->requests->cancel($solicitacao, $solicitacao->created_by);
        } catch (SignatureSessionException) {
            // Já concluída ou vencida: nada a cancelar.
        }
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
