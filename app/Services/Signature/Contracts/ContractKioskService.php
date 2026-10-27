<?php

namespace App\Services\Signature\Contracts;

use App\Exceptions\ContractException;
use App\Models\SignatureContract;
use App\Models\SignatureContractVersion;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Validação de contratos pela Gerência no TABLET DO BALCÃO (decisão do
 * usuário, 27/10/2026): mesma entrada da Assinatura da Diretoria — matrícula
 * e PIN do Lara —, numa tela própria, pensada para toque.
 *
 * O desenho da sessão é o do DirectorKioskService: cookie `lara_management`
 * (só o hash no cache, prazo curto renovado a cada uso), limite de tentativas
 * por MATRÍCULA (o tablet inteiro divide um IP) e, a cada passo, o servidor
 * confere de novo que o usuário está ativo e é do setor Gerência.
 *
 * Aqui só se lê, comenta e decide. Ajustar o texto continua no computador
 * (decisão do usuário). A decisão passa pelo mesmo ContractService da tela
 * web — as regras de estado são as mesmas.
 */
class ContractKioskService
{
    public const COOKIE = 'lara_management';

    private const CACHE_PREFIX = 'contratos-gerencia:sessao:';

    private const LIMIT_PREFIX = 'contratos-gerencia:login:';

    private const LIMIT_DECAY_SECONDS = 900;

    public function __construct(
        private ContractRenderer $renderer,
        private ContractDiff $diff,
    ) {
    }

    /**
     * Confere matrícula e PIN e abre a sessão. Uma resposta só para "não
     * existe", "inativo", "sem PIN" e "PIN errado"; "não é da Gerência" só
     * depois do PIN certo.
     *
     * @return array{user: User, token: string}
     *
     * @throws ContractException
     */
    public function login(string $matricula, string $pin, ?string $ip = null): array
    {
        $matricula = trim($matricula);
        $chave = self::LIMIT_PREFIX . Str::lower($matricula);
        $maximo = max(1, (int) config('signature.directors.max_login_attempts', 5));

        if (RateLimiter::tooManyAttempts($chave, $maximo)) {
            throw new ContractException('Muitas tentativas para esta matrícula. Tente de novo em '
                . (int) ceil(RateLimiter::availableIn($chave) / 60) . ' minuto(s).');
        }

        $user = $matricula !== '' ? $this->findByMatricula($matricula) : null;

        if (!$user || (int) $user->status_id !== 1 || !$user->checkPin($pin)) {
            RateLimiter::hit($chave, self::LIMIT_DECAY_SECONDS);

            Log::warning('Contratos: entrada da Gerência recusada no tablet.', ['matricula' => $matricula, 'ip' => $ip]);

            throw new ContractException('Matrícula ou PIN inválido.');
        }

        RateLimiter::clear($chave);

        if (!$user->isManagementMember()) {
            throw new ContractException('Seu usuário não é do setor ' . User::MANAGEMENT_SECTOR
                . ': a validação de contratos é da Gerência.');
        }

        $token = Str::random(64);

        Cache::put(self::CACHE_PREFIX . hash('sha256', $token), $user->id, $this->sessionTtl());

        Log::info('Contratos: Gerência entrou no tablet.', ['user_id' => $user->id, 'ip' => $ip]);

        return ['user' => $user, 'token' => $token];
    }

    /** O usuário da sessão, ou null (sem cookie, vencida, inativo, fora da Gerência). Renova o prazo. */
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

        $user = $this->findUser((int) $userId);

        if (!$user || (int) $user->status_id !== 1 || !$user->isManagementMember()) {
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
     * Os contratos aguardando a Gerência, do mais antigo para o mais novo
     * (quem espera há mais tempo, primeiro).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function pending(): Collection
    {
        return SignatureContract::where('status', SignatureContract::STATUS_IN_REVIEW)
            ->orderBy('submitted_at')
            ->orderBy('id')
            ->get()
            ->map(fn(SignatureContract $c) => [
                'id' => $c->id,
                'title' => $c->title,
                'counterparty' => $c->counterpartyName(),
                'sector' => $c->sector_name,
                'author' => $c->created_by_name,
                'version' => $c->current_version,
                'resubmission' => $c->current_version > 1,
                'addendum' => $c->isAddendum(),
                'submitted_at' => $c->submitted_at?->format('d/m/Y H:i'),
            ])
            ->values();
    }

    /**
     * Tudo o que a tela do contrato mostra: o texto, as cláusulas (para
     * comentar tocando nelas), o que mudou desde a última devolução, os
     * problemas que impedem aprovar e os anexos.
     *
     * @return array<string, mixed>
     *
     * @throws ContractException
     */
    public function detail(int $contractId): array
    {
        $contrato = $this->inReview($contractId);
        $conteudo = $contrato->content ?? [];

        $devolvida = $contrato->versions()->where('decision', SignatureContractVersion::DECISION_RETURNED)->first();
        $diff = $devolvida ? $this->diff->compare($devolvida->content ?? [], $conteudo) : null;

        $versao = $contrato->latestVersion();

        return [
            'id' => $contrato->id,
            'title' => $contrato->title,
            'counterparty' => $contrato->counterpartyName(),
            'sector' => $contrato->sector_name,
            'author' => $contrato->created_by_name,
            'version' => $contrato->current_version,
            'note' => $versao?->note,
            'html' => $this->renderer->html($conteudo, $contrato->number),
            'clauses' => collect($conteudo['clauses'] ?? [])
                ->map(fn(array $c, int $i) => ['uid' => $c['uid'], 'label' => 'Cláusula ' . ($i + 1) . ' – ' . ($c['title'] ?: 'sem título')])
                ->values(),
            'errors' => collect($this->renderer->problems($conteudo))->where('level', 'error')->pluck('message')->values(),
            'diff' => $diff && $diff['changed'] ? [
                'since' => $devolvida->version,
                'fields' => $diff['fields'],
                'removed' => $diff['removed'],
                'clauses' => collect($diff['clauses'])->reject(fn($c) => $c['status'] === 'same')->values(),
            ] : null,
            'attachments' => $contrato->attachments()->get()->map(fn($a) => [
                'id' => $a->id,
                'label' => $a->label,
                'mime' => $a->mime,
            ])->values(),
        ];
    }

    /**
     * O contrato em validação, ou recusa — a lista pode estar velha (outro
     * gerente já decidiu).
     *
     * @throws ContractException
     */
    public function inReview(int $contractId): SignatureContract
    {
        $contrato = SignatureContract::find($contractId);

        if (!$contrato || $contrato->status !== SignatureContract::STATUS_IN_REVIEW) {
            throw new ContractException('Este contrato não está mais aguardando validação. Atualize a lista.');
        }

        return $contrato;
    }

    public function sessionMinutes(): int
    {
        return max(1, (int) config('signature.contracts.kiosk_session_minutes', 20));
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

    protected function findUser(int $id): ?User
    {
        return User::find($id);
    }
}
