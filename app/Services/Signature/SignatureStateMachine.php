<?php

namespace App\Services\Signature;

use App\Exceptions\InvalidSignatureTransitionException;
use App\Models\SignatureAuditEvent;
use App\Models\SignatureDocument;
use App\Models\SignatureRequest;
use App\Models\SignatureSigner;
use Illuminate\Support\Facades\DB;

/**
 * O único lugar que muda o status de um documento, de um signatário ou de uma
 * solicitação de assinatura.
 *
 * Existe por dois motivos que andam juntos:
 *
 *  - **Transição válida.** Um documento cancelado não volta a aguardar
 *    assinatura; uma solicitação já consumida não é consumida de novo. Os
 *    mapas abaixo são a regra inteira, num lugar só, em vez de espalhada em
 *    `if`s por controllers.
 *  - **Auditoria.** Status e evento são gravados na MESMA transação. Não
 *    existe transição sem trilha: se a gravação do evento falhar, o status
 *    também não muda.
 *
 * Por isso nenhum outro ponto do módulo faz `update(['status' => ...])`.
 */
class SignatureStateMachine
{
    /**
     * De onde para onde um documento pode ir.
     *
     * `signed` só vai para `finalized`: depois que todos assinaram, o que
     * falta é o job montar o PDF final. Cancelar um documento já assinado
     * apagaria um ato jurídico que aconteceu — o caminho para desfazê-lo é
     * outro documento, não uma mudança de status.
     *
     * @var array<string, array<int, string>>
     */
    public const DOCUMENT_TRANSITIONS = [
        SignatureDocument::STATUS_DRAFT => [
            SignatureDocument::STATUS_AWAITING_SIGNATURE,
            SignatureDocument::STATUS_CANCELED,
            SignatureDocument::STATUS_EXPIRED,
        ],
        SignatureDocument::STATUS_AWAITING_SIGNATURE => [
            SignatureDocument::STATUS_SIGNED,
            SignatureDocument::STATUS_REFUSED,
            SignatureDocument::STATUS_CANCELED,
            SignatureDocument::STATUS_EXPIRED,
        ],
        SignatureDocument::STATUS_SIGNED => [
            SignatureDocument::STATUS_FINALIZED,
        ],
        SignatureDocument::STATUS_FINALIZED => [],
        SignatureDocument::STATUS_REFUSED => [],
        SignatureDocument::STATUS_CANCELED => [],
        SignatureDocument::STATUS_EXPIRED => [],
    ];

    /**
     * Solicitação (o QR). `superseded` é o destino de quem foi trocado por um
     * QR novo — diferente de `canceled`, que é o atendente desistindo do
     * atendimento. A diferença aparece na auditoria e explica, meses depois,
     * por que existem três QRs para o mesmo signatário.
     *
     * @var array<string, array<int, string>>
     */
    public const REQUEST_TRANSITIONS = [
        SignatureRequest::STATUS_PENDING => [
            SignatureRequest::STATUS_CONSUMED,
            SignatureRequest::STATUS_EXPIRED,
            SignatureRequest::STATUS_CANCELED,
            SignatureRequest::STATUS_SUPERSEDED,
        ],
        SignatureRequest::STATUS_CONSUMED => [
            SignatureRequest::STATUS_COMPLETED,
            SignatureRequest::STATUS_EXPIRED,
            SignatureRequest::STATUS_CANCELED,
        ],
        SignatureRequest::STATUS_COMPLETED => [],
        SignatureRequest::STATUS_EXPIRED => [],
        SignatureRequest::STATUS_CANCELED => [],
        SignatureRequest::STATUS_SUPERSEDED => [],
    ];

    /**
     * Signatário. Todos os desfechos são finais: quem assinou não desassina, e
     * quem recusou não é convidado de novo no mesmo documento.
     *
     * @var array<string, array<int, string>>
     */
    public const SIGNER_TRANSITIONS = [
        SignatureSigner::STATUS_PENDING => [
            SignatureSigner::STATUS_SIGNED,
            SignatureSigner::STATUS_REFUSED,
            SignatureSigner::STATUS_CANCELED,
            SignatureSigner::STATUS_EXPIRED,
        ],
        SignatureSigner::STATUS_SIGNED => [],
        SignatureSigner::STATUS_REFUSED => [],
        SignatureSigner::STATUS_CANCELED => [],
        SignatureSigner::STATUS_EXPIRED => [],
    ];

    public function __construct(private SignatureAuditor $auditor)
    {
    }

    public function canDocumentGoTo(string $from, string $to): bool
    {
        return in_array($to, self::DOCUMENT_TRANSITIONS[$from] ?? [], true);
    }

    public function canRequestGoTo(string $from, string $to): bool
    {
        return in_array($to, self::REQUEST_TRANSITIONS[$from] ?? [], true);
    }

    public function canSignerGoTo(string $from, string $to): bool
    {
        return in_array($to, self::SIGNER_TRANSITIONS[$from] ?? [], true);
    }

    /**
     * Move o documento e grava o evento.
     *
     * @param  array<string, mixed>  $attributes  colunas gravadas junto (frozen_at, canceled_reason…)
     * @param  array<string, mixed>  $context     o que vai para a auditoria
     *
     * @throws InvalidSignatureTransitionException
     */
    public function documentTo(
        SignatureDocument $document,
        string $to,
        string $event,
        array $attributes = [],
        array $context = [],
    ): SignatureDocument {
        if (!$this->canDocumentGoTo($document->status, $to)) {
            throw new InvalidSignatureTransitionException(
                "Documento {$document->id}: transição inválida de '{$document->status}' para '{$to}'."
            );
        }

        return DB::transaction(function () use ($document, $to, $event, $attributes, $context) {
            $from = $document->status;

            $document->forceFill(array_merge($attributes, ['status' => $to]))->save();

            $this->auditor->record($document, $event, $this->withTransition($context, $from, $to));

            return $document;
        });
    }

    /**
     * Move a solicitação e grava o evento.
     *
     * O documento da auditoria vem pelo signatário — a trilha é do DOCUMENTO,
     * e uma solicitação sem documento não existe.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $context
     *
     * @throws InvalidSignatureTransitionException
     */
    public function requestTo(
        SignatureRequest $request,
        string $to,
        string $event,
        array $attributes = [],
        array $context = [],
    ): SignatureRequest {
        if (!$this->canRequestGoTo($request->status, $to)) {
            throw new InvalidSignatureTransitionException(
                "Solicitação {$request->id}: transição inválida de '{$request->status}' para '{$to}'."
            );
        }

        return DB::transaction(function () use ($request, $to, $event, $attributes, $context) {
            $from = $request->status;

            $request->forceFill(array_merge($attributes, ['status' => $to]))->save();

            $signer = $request->signer;

            $this->auditor->record(
                $signer->signature_document_id,
                $event,
                array_merge(
                    ['signer' => $signer->id, 'request' => $request->id],
                    $this->withTransition($context, $from, $to),
                ),
            );

            return $request;
        });
    }

    /**
     * Move o signatário e grava o evento.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $context
     *
     * @throws InvalidSignatureTransitionException
     */
    public function signerTo(
        SignatureSigner $signer,
        string $to,
        string $event,
        array $attributes = [],
        array $context = [],
    ): SignatureSigner {
        if (!$this->canSignerGoTo($signer->status, $to)) {
            throw new InvalidSignatureTransitionException(
                "Signatário {$signer->id}: transição inválida de '{$signer->status}' para '{$to}'."
            );
        }

        return DB::transaction(function () use ($signer, $to, $event, $attributes, $context) {
            $from = $signer->status;

            $signer->forceFill(array_merge($attributes, ['status' => $to]))->save();

            $this->auditor->record(
                $signer->signature_document_id,
                $event,
                array_merge(['signer' => $signer->id], $this->withTransition($context, $from, $to)),
            );

            return $signer;
        });
    }

    /**
     * Registra um fato que NÃO muda status — visualização, identidade
     * confirmada, releitura de QR bloqueada.
     *
     * Passa por aqui, e não direto pelo auditor, para que o módulo inteiro
     * tenha uma porta só para a trilha.
     *
     * @param  array<string, mixed>  $context
     */
    public function note(SignatureDocument|int $document, string $event, array $context = []): SignatureAuditEvent
    {
        return $this->auditor->record($document, $event, $context);
    }

    /**
     * O de-para da transição entra no payload da auditoria. É o que permite
     * reconstruir o caminho do documento sem inferir nada da ordem dos eventos.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function withTransition(array $context, string $from, string $to): array
    {
        $context['payload'] = array_merge(
            ['de' => $from, 'para' => $to],
            $context['payload'] ?? [],
        );

        return $context;
    }
}
