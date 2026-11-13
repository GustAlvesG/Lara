<?php

namespace App\Services\Signature;

use App\Models\SignatureAuditEvent;
use App\Models\SignatureDocument;
use Illuminate\Support\Carbon;

/**
 * Os prazos do documento aguardando assinatura (decisão do usuário,
 * 13/11/2026):
 *
 *  - balcão: `document_ttl_days` (7 dias) a partir do congelamento;
 *  - gov.br: `govbr.ttl_days` (7 dias) a partir da preparação;
 *  - link remoto: `remote.link_ttl_days` (7 dias) a partir do envio — o
 *    reenvio dá mais 7 a partir dele, e o documento é estendido até o fim do
 *    link (RemoteSignatureService::send);
 *  - diretoria: quando só falta diretor assinar, o documento fica SEM prazo
 *    (`expires_at` nulo). Enquanto falta alguém de fora da diretoria, vale o
 *    prazo do documento.
 *
 * O prazo é retirado no momento em que o último de fora da diretoria assina
 * (SignatureStateMachine::signerTo chama `dropIfOnlyDirectorsLeft`), com
 * evento na trilha. O `signature:expire` confere de novo antes de expirar:
 * um documento que já estava só com a diretoria quando a regra entrou perde
 * o prazo em vez de expirar.
 */
class SignatureDeadlineService
{
    public function __construct(private SignatureStateMachine $states)
    {
    }

    /** O prazo que começa no congelamento. null = sem prazo (só diretoria). */
    public function forFreeze(SignatureDocument $document): ?Carbon
    {
        return $document->waitsOnlyForDirectors()
            ? null
            : now()->addDays(max(1, (int) config('signature.document_ttl_days', 7)));
    }

    /** O prazo que começa na preparação para o gov.br. null = sem prazo. */
    public function forGovbr(SignatureDocument $document): ?Carbon
    {
        return $document->waitsOnlyForDirectors()
            ? null
            : now()->addDays(max(1, (int) config('signature.govbr.ttl_days', 7)));
    }

    /**
     * Os documentos que expiraram nos últimos `$days` dias e podem ser
     * reabertos: congelados, expirados, com o vencimento dentro da janela.
     *
     * @return \Illuminate\Support\Collection<int, SignatureDocument>
     */
    public function recentlyExpired(int $days, bool $contractsOnly = false): \Illuminate\Support\Collection
    {
        return SignatureDocument::with('signers')
            ->where('status', SignatureDocument::STATUS_EXPIRED)
            ->whereNotNull('frozen_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '>=', now()->subDays(max(1, $days)))
            ->when($contractsOnly, fn($q) => $q->whereHas('contract'))
            ->orderBy('expires_at')
            ->get();
    }

    /**
     * Reabre um documento expirado: os signatários que expiraram com ele
     * voltam a pendentes e o documento volta a aguardar assinatura, com o
     * prazo de hoje — 7 dias (do gov.br, se foi preparado para lá) ou nenhum,
     * se só falta a diretoria. Tudo com evento na trilha. QR e link antigos
     * continuam vencidos: o atendente gera outro.
     */
    public function reactivate(SignatureDocument $document, ?string $reason = null): SignatureDocument
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($document, $reason) {
            $document = SignatureDocument::with('signers')->lockForUpdate()->findOrFail($document->id);
            $anterior = $document->expires_at;

            foreach ($document->signers->where('status', \App\Models\SignatureSigner::STATUS_EXPIRED) as $signer) {
                $this->states->signerTo(
                    $signer,
                    \App\Models\SignatureSigner::STATUS_PENDING,
                    SignatureAuditEvent::EVENT_REACTIVATED,
                    [],
                    ['actor_type' => SignatureAuditEvent::ACTOR_SYSTEM],
                );
            }

            $document->load('signers');
            $prazo = $document->govbr_sent_at ? $this->forGovbr($document) : $this->forFreeze($document);

            return $this->states->documentTo(
                $document,
                SignatureDocument::STATUS_AWAITING_SIGNATURE,
                SignatureAuditEvent::EVENT_REACTIVATED,
                ['expires_at' => $prazo],
                [
                    'actor_type' => SignatureAuditEvent::ACTOR_SYSTEM,
                    'payload' => [
                        'expirou_em' => $anterior?->toDateTimeString(),
                        'novo_prazo' => $prazo?->toDateTimeString() ?? 'sem prazo (só falta a diretoria)',
                        'motivo' => $reason,
                    ],
                ],
            );
        });
    }

    /**
     * Retira o prazo se o documento, aguardando assinatura, agora só espera
     * pela diretoria. Devolve se retirou.
     *
     * @param  array<string, mixed>  $context  o que vai para a auditoria
     */
    public function dropIfOnlyDirectorsLeft(SignatureDocument $document, array $context = []): bool
    {
        $document = SignatureDocument::with('signers')->find($document->id);

        if (!$document
            || $document->status !== SignatureDocument::STATUS_AWAITING_SIGNATURE
            || $document->expires_at === null
            || !$document->waitsOnlyForDirectors()) {
            return false;
        }

        $anterior = $document->expires_at;

        $document->forceFill(['expires_at' => null])->save();

        $this->states->note($document, SignatureAuditEvent::EVENT_DEADLINE_REMOVED, array_merge(
            ['actor_type' => SignatureAuditEvent::ACTOR_SYSTEM],
            $context,
            ['payload' => ['prazo_anterior' => $anterior->toDateTimeString()]],
        ));

        return true;
    }
}
