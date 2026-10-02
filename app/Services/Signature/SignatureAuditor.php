<?php

namespace App\Services\Signature;

use App\Models\SignatureAuditEvent;
use App\Models\SignatureDocument;
use App\Models\SignatureRequest;
use App\Models\SignatureSigner;
use App\Support\Cpf;
use Illuminate\Support\Facades\DB;

/**
 * Escreve a trilha de auditoria do módulo de assinatura — o único lugar do
 * sistema que insere em `signature_audit_events`.
 *
 * Cada linha guarda o hash da anterior DAQUELE documento. Alterar uma linha
 * por fora da aplicação quebra a conferência dela em diante, e `verify()`
 * aponta onde. Não impede a adulteração — torna-a detectável, que é o que uma
 * trilha de auditoria pode honestamente prometer.
 *
 * O que NUNCA entra no payload: o token do QR (nem em claro nem em hash) e o
 * CPF sem máscara.
 */
class SignatureAuditor
{
    /**
     * Grava um evento e devolve a linha criada.
     *
     * @param  array{
     *     signer?: SignatureSigner|int|null,
     *     request?: SignatureRequest|int|null,
     *     payload?: array<string, mixed>,
     *     actor_type?: string,
     *     actor_id?: int|null,
     *     ip?: string|null,
     *     user_agent?: string|null,
     * }  $context
     */
    public function record(SignatureDocument|int $document, string $event, array $context = []): SignatureAuditEvent
    {
        $documentId = $document instanceof SignatureDocument ? $document->id : $document;

        $signer = $context['signer'] ?? null;
        $request = $context['request'] ?? null;

        $actor = $this->resolveActor($context);
        $origin = $this->resolveOrigin($context);

        return DB::transaction(function () use ($documentId, $event, $context, $signer, $request, $actor, $origin) {
            /*
             | A ponta da cadeia daquele documento, travada para a duração da
             | transação: duas gravações simultâneas no MESMO documento (o
             | tablet assinando enquanto o atendente cancela) encadeariam as
             | duas sobre o mesmo anterior, e uma delas ficaria órfã.
             */
            $previous = SignatureAuditEvent::where('signature_document_id', $documentId)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            $model = new SignatureAuditEvent([
                'signature_document_id' => $documentId,
                'signature_signer_id' => $signer instanceof SignatureSigner ? $signer->id : $signer,
                'signature_request_id' => $request instanceof SignatureRequest ? $request->id : $request,
                'event' => $event,
                'payload' => $this->sanitize($context['payload'] ?? []),
                'actor_type' => $actor['type'],
                'actor_id' => $actor['id'],
                'ip' => $origin['ip'],
                'user_agent' => $origin['user_agent'],
                'occurred_at' => now(),
            ]);

            $model->previous_hash = $previous?->hash;
            $model->hash = $model->computeHash();
            $model->save();

            return $model;
        });
    }

    /**
     * Confere a cadeia de um documento inteiro.
     *
     * @return array{valid: bool, checked: int, broken_at: ?int}
     */
    public function verify(SignatureDocument|int $document): array
    {
        $documentId = $document instanceof SignatureDocument ? $document->id : $document;

        $previousHash = null;
        $checked = 0;

        foreach (SignatureAuditEvent::where('signature_document_id', $documentId)->orderBy('id')->cursor() as $event) {
            $checked++;

            if ($event->previous_hash !== $previousHash || $event->hash !== $event->computeHash()) {
                return ['valid' => false, 'checked' => $checked, 'broken_at' => $event->id];
            }

            $previousHash = $event->hash;
        }

        return ['valid' => true, 'checked' => $checked, 'broken_at' => null];
    }

    /**
     * Tira do payload o que não pode ser registrado: CPF vira mascarado, e
     * qualquer coisa que se pareça com token some.
     *
     * A varredura é recursiva porque o payload às vezes carrega o retrato de
     * um signatário inteiro, e o CPF estaria um nível abaixo.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private function sanitize(array $payload): ?array
    {
        if ($payload === []) {
            return null;
        }

        $limpo = [];

        foreach ($payload as $chave => $valor) {
            $nome = mb_strtolower((string) $chave);

            if (str_contains($nome, 'token') || str_contains($nome, 'password') || str_contains($nome, 'pin')) {
                continue;
            }

            if (is_array($valor)) {
                $limpo[$chave] = $this->sanitize($valor) ?? [];

                continue;
            }

            $limpo[$chave] = str_contains($nome, 'cpf') ? Cpf::mask((string) $valor) : $valor;
        }

        return $limpo;
    }

    /**
     * Quem agiu. O tablet é sempre informado explicitamente pelo chamador — a
     * sessão do quiosque não é a sessão web, e `auth()` lá dentro é sempre
     * anônimo, o que faria todo ato do tablet virar "sistema".
     *
     * @param  array<string, mixed>  $context
     * @return array{type: string, id: ?int}
     */
    private function resolveActor(array $context): array
    {
        if (isset($context['actor_type'])) {
            return [
                'type' => $context['actor_type'],
                'id' => $context['actor_id'] ?? null,
            ];
        }

        $user = auth()->user();

        if ($user) {
            return ['type' => SignatureAuditEvent::ACTOR_USER, 'id' => $user->id];
        }

        return ['type' => SignatureAuditEvent::ACTOR_SYSTEM, 'id' => null];
    }

    /**
     * IP e user agent. Só são lidos da requisição quando existe uma: num job
     * de fila ou num comando agendado, o "IP" seria o do próprio servidor, o
     * que é pior do que não registrar nada.
     *
     * @param  array<string, mixed>  $context
     * @return array{ip: ?string, user_agent: ?string}
     */
    private function resolveOrigin(array $context): array
    {
        if (array_key_exists('ip', $context) || array_key_exists('user_agent', $context)) {
            return [
                'ip' => $context['ip'] ?? null,
                'user_agent' => $this->truncate($context['user_agent'] ?? null),
            ];
        }

        if (app()->runningInConsole()) {
            return ['ip' => null, 'user_agent' => null];
        }

        $request = request();

        return [
            'ip' => $request?->ip(),
            'user_agent' => $this->truncate($request?->userAgent()),
        ];
    }

    private function truncate(?string $value): ?string
    {
        return $value === null ? null : mb_substr($value, 0, 255);
    }
}
