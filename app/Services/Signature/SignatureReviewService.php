<?php

namespace App\Services\Signature;

use App\Exceptions\SignatureDocumentLockedException;
use App\Models\SignatureAuditEvent;
use App\Models\SignatureDocument;
use App\Models\SignatureReview;
use Illuminate\Support\Facades\DB;

/**
 * A revisão interna do documento assinado.
 *
 * Todo documento concluído passa por ela: outra pessoa — que não acompanhou a
 * assinatura — confere se os processos internos daquele atendimento foram
 * feitos. O que conferir vem do modelo (`review_items`); a revisão marca cada
 * item, dá o resultado ("Tudo em ordem" ou "Com pendência") e, com pendência,
 * diz qual. Com pendência, o documento continua na fila até uma revisão em
 * ordem.
 *
 * As duas regras da revisão comum — só do dia seguinte à conclusão em diante,
 * e nunca por quem acompanhou — caem para quem tem a permissão de coordenação
 * (`assinatura.revisar-coordenacao`). Quando isso acontece, a revisão grava
 * que foi antecipada (`early`) ou do próprio documento (`own`).
 *
 * Não muda o documento assinado: nem o PDF, nem o hash. A revisão é registro
 * interno, e entra na trilha de auditoria.
 */
class SignatureReviewService
{
    public function __construct(private SignatureStateMachine $states)
    {
    }

    /**
     * Os itens da revisão na forma gravada no modelo: chave estável e rótulo.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array{key: string, label: string}>
     */
    public static function normalize(array $rows): array
    {
        return array_map(
            fn(array $item) => ['key' => $item['key'], 'label' => $item['label']],
            SignatureAttachmentService::normalize($rows, 'rev'),
        );
    }

    /**
     * Por que esta pessoa não pode revisar este documento agora. null = pode.
     *
     * @param  bool  $coordination  tem a permissão de coordenação
     */
    public function blockReason(SignatureDocument $document, ?int $userId, bool $coordination): ?string
    {
        if ($document->status !== SignatureDocument::STATUS_FINALIZED) {
            return 'Só documento concluído passa pela revisão.';
        }

        if ($document->review_status === SignatureReview::RESULT_OK) {
            return 'Este documento já foi revisado e está em ordem.';
        }

        if ($coordination) {
            return null;
        }

        if ($this->isEarly($document)) {
            return 'Disponível para revisão a partir de ' . $document->reviewAvailableAt()->format('d/m/Y') . '.';
        }

        if ($this->isOwn($document, $userId)) {
            return 'Você acompanhou a assinatura deste documento: a revisão é de outra pessoa.';
        }

        return null;
    }

    /**
     * Registra a revisão.
     *
     * @param  array<int, string>  $done  chaves dos itens marcados como feitos
     *
     * @throws SignatureDocumentLockedException
     */
    public function review(
        SignatureDocument $document,
        string $result,
        array $done,
        ?string $notes,
        ?int $userId,
        ?string $userName,
        bool $coordination,
    ): SignatureReview {
        if ($motivo = $this->blockReason($document, $userId, $coordination)) {
            throw new SignatureDocumentLockedException($motivo);
        }

        if (!array_key_exists($result, SignatureReview::RESULT_LABELS)) {
            throw new SignatureDocumentLockedException('Escolha o resultado da revisão.');
        }

        $notes = trim((string) $notes) ?: null;

        $itens = array_map(fn(array $item) => $item + [
            'done' => in_array($item['key'], $done, true),
        ], $document->template?->declaredReviewItems() ?? []);

        $faltando = array_values(array_filter($itens, fn(array $item) => !$item['done']));

        if ($result === SignatureReview::RESULT_OK && $faltando !== []) {
            throw new SignatureDocumentLockedException(
                'Para "Tudo em ordem", todos os itens precisam estar feitos. Falta: '
                    . implode(', ', array_column($faltando, 'label')) . '. Se não foram feitos, marque "Com pendência".'
            );
        }

        if ($result === SignatureReview::RESULT_ISSUES && $notes === null) {
            throw new SignatureDocumentLockedException('Com pendência, diga na observação o que falta fazer.');
        }

        $antecipada = $this->isEarly($document);
        $propria = $this->isOwn($document, $userId);

        return DB::transaction(function () use ($document, $result, $itens, $notes, $userId, $userName, $antecipada, $propria, $faltando) {
            $revisao = SignatureReview::create([
                'signature_document_id' => $document->id,
                'result' => $result,
                'items' => $itens,
                'notes' => $notes,
                'early' => $antecipada,
                'own' => $propria,
                'reviewed_by' => $userId,
                'reviewed_by_name' => $userName,
            ]);

            $document->forceFill(['review_status' => $result, 'reviewed_at' => now()])->save();

            $this->states->note($document, SignatureAuditEvent::EVENT_REVIEWED, [
                'actor_id' => $userId,
                // Os rótulos, e não a observação: a trilha é impressa, e a
                // observação é texto livre de uso interno.
                'payload' => array_filter([
                    'revisao' => $revisao->id,
                    'resultado' => $result,
                    'itens' => count($itens),
                    'pendentes' => array_column($faltando, 'label') ?: null,
                    'antecipada' => $antecipada ?: null,
                    'propria' => $propria ?: null,
                    'revisado_por' => $userName,
                ], fn($v) => $v !== null),
            ]);

            return $revisao;
        });
    }

    /** Antes do dia seguinte à conclusão. */
    public function isEarly(SignatureDocument $document): bool
    {
        $disponivel = $document->reviewAvailableAt();

        return $disponivel !== null && now()->lt($disponivel);
    }

    /** A pessoa acompanhou a assinatura (gerou o documento, um QR, um convite ou uma conferência do gov.br). */
    public function isOwn(SignatureDocument $document, ?int $userId): bool
    {
        return $userId !== null && in_array($userId, $document->involvedUserIds(), true);
    }
}
