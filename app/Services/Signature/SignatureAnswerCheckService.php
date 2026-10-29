<?php

namespace App\Services\Signature;

use App\Exceptions\SignatureSessionException;
use App\Models\SignatureAnswerCheck;
use App\Models\SignatureAuditEvent;
use App\Models\SignatureDocument;
use App\Models\SignatureRequest;
use App\Models\SignatureSigner;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Conferência das respostas do tablet pelo atendente.
 *
 * Vale para documento com pergunta a quem assina, liberado por QR do
 * atendente no balcão, enquanto as respostas ainda podem mudar (ninguém
 * assinou). Ficam de fora o link remoto (não há atendente ao lado), a
 * Diretoria (o próprio diretor abre, com matrícula e PIN) e o Termo de
 * Menores (autoatendimento) — decisão do usuário, 29/10/2026.
 *
 * O fluxo:
 *
 *  1. Enquanto a pessoa digita, o tablet manda o RASCUNHO a cada campo
 *     (`saveDraft`). Fica só no cache do servidor, ligado à liberação: não
 *     entra no documento, não vai à trilha e some com a sessão. O painel
 *     mostra o rascunho se atualizando.
 *  2. "Continuar" grava as respostas como sempre (SignatureSigningDataService::
 *     answer): PDF e hash refeitos. A conferência fica PENDENTE; a pessoa lê o
 *     documento, e o tablet segura o aceite.
 *  3. O atendente aprova, devolve com observação (o tablet volta ao
 *     formulário com o recado) ou corrige ele mesmo (PDF refeito, a pessoa lê
 *     de novo) — a correção já aprova.
 *
 * Quem garante é o servidor: `assertApproved` no `capture`. A aprovação vale
 * para o `original_sha256` em que foi dada; resposta mudada depois é outro
 * original, e a conferência volta a ser exigida sem ninguém apagar nada.
 */
class SignatureAnswerCheckService
{
    public const STATUS_FILLING = 'filling';
    public const STATUS_PENDING = 'pending';
    public const STATUS_RETURNED = 'returned';
    public const STATUS_APPROVED = 'approved';

    public const STATUS_LABELS = [
        self::STATUS_FILLING => 'Preenchendo no tablet',
        self::STATUS_PENDING => 'Aguardando a sua conferência',
        self::STATUS_RETURNED => 'Devolvido para correção',
        self::STATUS_APPROVED => 'Conferido e aprovado',
    ];

    public function __construct(
        private SignatureSigningDataService $signingData,
        private SignatureStateMachine $states,
    ) {
    }

    /** Esta liberação precisa da conferência do atendente para assinar? */
    public function requiredFor(SignatureRequest $request): bool
    {
        $documento = $request->signer?->document;

        return $documento !== null
            && !$request->isRemote()
            && $request->identity_method !== SignatureRequest::IDENTITY_METHOD_DIRECTOR_PIN
            && $this->documentRequires($documento);
    }

    /**
     * O documento, pelo lado dele: tem pergunta a quem assina, as respostas
     * ainda podem mudar e não é Termo de Menores.
     */
    public function documentRequires(SignatureDocument $document): bool
    {
        return $document->signerFields() !== []
            && $document->signingDataIsOpen()
            && !$document->isMinorTerm();
    }

    /** Onde a conferência está. */
    public function status(SignatureDocument $document): string
    {
        if ($document->signing_answered_at === null) {
            return self::STATUS_FILLING;
        }

        $ultima = $this->latest($document);

        if ($ultima?->action === SignatureAnswerCheck::ACTION_APPROVED
            && $ultima->document_sha256 === $document->original_sha256) {
            return self::STATUS_APPROVED;
        }

        // Devolvido e a pessoa ainda não reenviou: o envio seguinte grava
        // `signing_answered_at` depois da devolução.
        if ($ultima?->action === SignatureAnswerCheck::ACTION_RETURNED
            && $ultima->created_at !== null
            && $ultima->created_at->greaterThanOrEqualTo($document->signing_answered_at)) {
            return self::STATUS_RETURNED;
        }

        return self::STATUS_PENDING;
    }

    public function latest(SignatureDocument $document): ?SignatureAnswerCheck
    {
        return SignatureAnswerCheck::where('signature_document_id', $document->id)->orderByDesc('id')->first();
    }

    /**
     * Trava do servidor no ato de assinar.
     *
     * @throws SignatureSessionException
     */
    public function assertApproved(SignatureRequest $request): void
    {
        if (!$this->requiredFor($request)) {
            return;
        }

        $documento = $request->signer->document;

        if ($this->status($documento) !== self::STATUS_APPROVED) {
            throw new SignatureSessionException(
                'O atendente ainda não conferiu as suas respostas. Aguarde a conferência para assinar.',
                409,
            );
        }
    }

    /**
     * O rascunho do formulário, campo a campo, enquanto a pessoa digita. Só
     * as chaves que o documento pergunta, só texto, com teto de tamanho.
     *
     * @param  array<string, mixed>  $answers
     */
    public function saveDraft(SignatureRequest $request, array $answers): void
    {
        if (!$this->requiredFor($request)) {
            return;
        }

        $chaves = array_column($request->signer->document->signerFields(), 'key');
        $rascunho = [];

        foreach ($chaves as $chave) {
            if (!array_key_exists($chave, $answers)) {
                continue;
            }

            $valor = $answers[$chave];
            $rascunho[$chave] = is_array($valor)
                ? array_values(array_map(fn($v) => mb_substr((string) $v, 0, 200), array_filter($valor, 'is_scalar')))
                : mb_substr(is_scalar($valor) ? (string) $valor : '', 0, 2000);
        }

        Cache::put(
            $this->draftKey($request),
            ['answers' => $rascunho, 'at' => now()->format('H:i:s')],
            now()->addMinutes(max(1, (int) config('signature.session_ttl_minutes', 15))),
        );
    }

    /** @return array{answers: array<string, mixed>, at: string}|null */
    public function draft(SignatureRequest $request): ?array
    {
        return Cache::get($this->draftKey($request));
    }

    public function forgetDraft(SignatureRequest $request): void
    {
        Cache::forget($this->draftKey($request));
    }

    /**
     * Aprova as respostas como estão agora.
     *
     * @throws SignatureSessionException
     */
    public function approve(SignatureDocument $document, ?int $userId, ?string $userName): SignatureAnswerCheck
    {
        // Devolvido e o atendente reconsiderou: aprova o que já estava gravado.
        $this->assertCanAct($document, [self::STATUS_PENDING, self::STATUS_RETURNED]);

        return DB::transaction(function () use ($document, $userId, $userName) {
            $conferencia = $this->record($document, SignatureAnswerCheck::ACTION_APPROVED, $userId, $userName);

            $this->states->note($document, SignatureAuditEvent::EVENT_ANSWERS_APPROVED, [
                'request' => $conferencia->signature_request_id,
                'actor_type' => SignatureAuditEvent::ACTOR_USER,
                'actor_id' => $userId,
                'payload' => ['conferido_por' => $userName, 'hash' => $document->original_sha256],
            ]);

            return $conferencia;
        });
    }

    /**
     * Devolve à pessoa, com o recado do atendente. A observação vai ao tablet
     * e fica na conferência, mas NÃO na trilha (o manifesto é impresso).
     *
     * @throws SignatureSessionException
     */
    public function giveBack(SignatureDocument $document, string $note, ?int $userId, ?string $userName): SignatureAnswerCheck
    {
        $this->assertCanAct($document, [self::STATUS_PENDING, self::STATUS_APPROVED]);

        return DB::transaction(function () use ($document, $note, $userId, $userName) {
            $conferencia = $this->record($document, SignatureAnswerCheck::ACTION_RETURNED, $userId, $userName, [
                'note' => mb_substr(trim($note), 0, 500),
            ]);

            $this->states->note($document, SignatureAuditEvent::EVENT_ANSWERS_RETURNED, [
                'request' => $conferencia->signature_request_id,
                'actor_type' => SignatureAuditEvent::ACTOR_USER,
                'actor_id' => $userId,
                'payload' => ['devolvido_por' => $userName],
            ]);

            return $conferencia;
        });
    }

    /**
     * O atendente corrige as respostas e, com isso, aprova o resultado. O PDF é
     * refeito, e o tablet manda a pessoa ler de novo antes de assinar.
     *
     * @param  array<string, mixed>  $answers
     *
     * @throws SignatureSessionException
     * @throws \App\Exceptions\SignatureFormException
     */
    public function correct(SignatureDocument $document, array $answers, ?int $userId, ?string $userName): SignatureAnswerCheck
    {
        $this->assertCanAct($document, [self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_RETURNED]);

        $contexto = [
            'request' => $this->liveRequest($document)?->id,
            'actor_type' => SignatureAuditEvent::ACTOR_USER,
            'actor_id' => $userId,
        ];

        [$documento, $alteradas] = $this->signingData->correct($document, $answers, $contexto);
        $documento->refresh();

        if ($alteradas !== []) {
            $this->record($documento, SignatureAnswerCheck::ACTION_CORRECTED, $userId, $userName, [
                'field_keys' => $alteradas,
            ]);
        }

        return $this->approve($documento, $userId, $userName);
    }

    /**
     * O que o tablet precisa saber: se há conferência, onde ela está e, se
     * devolvida, o recado. Null quando esta liberação não passa por ela.
     *
     * @return array{required: bool, status: string, note: ?string}|null
     */
    public function kioskPayload(SignatureRequest $request): ?array
    {
        if (!$this->requiredFor($request)) {
            return null;
        }

        $documento = $request->signer->document;
        $status = $this->status($documento);

        return [
            'required' => true,
            'status' => $status,
            'note' => $status === self::STATUS_RETURNED ? $this->latest($documento)?->note : null,
        ];
    }

    /**
     * O cartão "Conferência das respostas" da tela do atendente. Null quando
     * não há conferência a fazer agora.
     *
     * @return array<string, mixed>|null
     */
    public function panelPayload(SignatureDocument $document): ?array
    {
        if (!$this->documentRequires($document)) {
            return null;
        }

        $liberacao = $this->liveRequest($document);
        $status = $this->status($document);
        $rascunho = $liberacao && $status === self::STATUS_FILLING ? $this->draft($liberacao) : null;

        // Sem ninguém no tablet e sem resposta gravada, não há o que conferir.
        if (!$liberacao && $document->signing_answered_at === null) {
            return null;
        }

        $valores = $document->signing_data ?? [];
        $ultima = $this->latest($document);

        return [
            'status' => $status,
            'status_label' => self::STATUS_LABELS[$status],
            'in_tablet' => $liberacao !== null,
            'draft_at' => $rascunho['at'] ?? null,
            'note' => $status === self::STATUS_RETURNED ? $ultima?->note : null,
            'checked_by' => $status === self::STATUS_APPROVED ? $ultima?->user_name : null,
            'checked_at' => $status === self::STATUS_APPROVED ? $ultima?->created_at?->format('H:i:s') : null,
            'fields' => array_map(function (array $campo) use ($rascunho, $valores) {
                if ($rascunho !== null) {
                    $bruto = $rascunho['answers'][$campo['key']] ?? null;
                    $texto = is_array($bruto) ? implode(', ', $bruto) : (string) ($bruto ?? '');
                } else {
                    $texto = SignatureFieldTypes::format($campo, $valores[$campo['key']] ?? null);
                }

                return [
                    'key' => $campo['key'],
                    'question' => $campo['question'],
                    'type' => $campo['type'],
                    'required' => $campo['required'],
                    'options' => $campo['options'],
                    'answer' => $texto,
                    // O valor no formato do formulário, para o atendente corrigir.
                    'input' => SignatureFieldTypes::inputValue($campo, $valores[$campo['key']] ?? null),
                ];
            }, $document->signerFields()),
        ];
    }

    /**
     * A liberação do balcão em uso no tablet agora, se houver.
     */
    public function liveRequest(SignatureDocument $document): ?SignatureRequest
    {
        return SignatureRequest::whereIn(
            'signature_signer_id',
            $document->signers()->where('status', SignatureSigner::STATUS_PENDING)->pluck('id'),
        )
            ->where('status', SignatureRequest::STATUS_CONSUMED)
            // Só a do balcão: nem o link remoto, nem a entrada do diretor.
            ->where(fn($q) => $q->whereNull('identity_method')->orWhereNotIn('identity_method', [
                SignatureRequest::IDENTITY_METHOD_DIRECTOR_PIN,
                SignatureRequest::IDENTITY_METHOD_REMOTE_LINK,
            ]))
            ->orderByDesc('id')
            ->get()
            ->first(fn(SignatureRequest $r) => $r->secondsToSessionEnd() > 0);
    }

    /**
     * @param  array<int, string>  $permitidos
     *
     * @throws SignatureSessionException
     */
    private function assertCanAct(SignatureDocument $document, array $permitidos): void
    {
        if (!$this->documentRequires($document)) {
            throw new SignatureSessionException(
                'Não há respostas a conferir neste documento (ou alguém já assinou, e elas não mudam mais).',
                409,
            );
        }

        $status = $this->status($document);

        if (!in_array($status, $permitidos, true)) {
            throw new SignatureSessionException(match ($status) {
                self::STATUS_FILLING => 'A pessoa ainda está respondendo no tablet. Aguarde ela tocar em "Continuar".',
                self::STATUS_RETURNED => 'As respostas foram devolvidas: aguarde a pessoa corrigir no tablet.',
                self::STATUS_APPROVED => 'As respostas já foram aprovadas.',
                default => 'Não é possível conferir agora.',
            }, 409);
        }
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function record(
        SignatureDocument $document,
        string $action,
        ?int $userId,
        ?string $userName,
        array $extra = [],
    ): SignatureAnswerCheck {
        return SignatureAnswerCheck::create(array_merge([
            'signature_document_id' => $document->id,
            'signature_request_id' => $this->liveRequest($document)?->id,
            'action' => $action,
            'document_sha256' => $document->original_sha256,
            'user_id' => $userId,
            'user_name' => $userName,
        ], $extra));
    }

    private function draftKey(SignatureRequest $request): string
    {
        return 'signature:answer-draft:' . $request->id;
    }
}
