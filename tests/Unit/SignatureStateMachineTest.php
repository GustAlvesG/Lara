<?php

namespace Tests\Unit;

use App\Exceptions\InvalidSignatureTransitionException;
use App\Models\SignatureAuditEvent;
use App\Models\SignatureDocument;
use App\Models\SignatureRequest;
use App\Models\SignatureSigner;
use App\Services\Signature\SignatureStateMachine;
use Tests\Concerns\CreatesSignatureSchema;
use Tests\TestCase;

/**
 * A máquina de estados é a única porta para mudar status no módulo de
 * assinatura. Dois contratos são testados aqui:
 *
 *  - o que ela RECUSA — um documento cancelado não volta a aguardar
 *    assinatura, um QR já consumido não é consumido de novo;
 *  - o que ela GRAVA — toda transição aceita deixa um evento na trilha, com o
 *    de-para. Uma transição que mudasse o status sem registrar seria pior que
 *    uma transição proibida: o documento andaria sem história.
 *
 * Sem `RefreshDatabase` e sem tocar no model `User` — ver CreatesSignatureSchema.
 */
class SignatureStateMachineTest extends TestCase
{
    use CreatesSignatureSchema;

    private SignatureStateMachine $machine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSignatureSchema();

        $this->machine = app(SignatureStateMachine::class);
    }

    public function test_documento_segue_o_caminho_normal_e_deixa_trilha(): void
    {
        $documento = $this->criaDocumentoDeAssinatura();

        $this->machine->documentTo(
            $documento,
            SignatureDocument::STATUS_AWAITING_SIGNATURE,
            SignatureAuditEvent::EVENT_FROZEN,
            ['frozen_at' => now()],
        );

        $this->assertSame(SignatureDocument::STATUS_AWAITING_SIGNATURE, $documento->fresh()->status);

        $evento = SignatureAuditEvent::where('signature_document_id', $documento->id)->latest('id')->first();

        $this->assertSame(SignatureAuditEvent::EVENT_FROZEN, $evento->event);
        $this->assertSame(SignatureDocument::STATUS_DRAFT, $evento->payload['de']);
        $this->assertSame(SignatureDocument::STATUS_AWAITING_SIGNATURE, $evento->payload['para']);
    }

    public function test_documento_cancelado_nao_volta_para_aguardando_assinatura(): void
    {
        $documento = $this->criaDocumentoDeAssinatura();

        $this->machine->documentTo(
            $documento,
            SignatureDocument::STATUS_CANCELED,
            SignatureAuditEvent::EVENT_CANCELED,
        );

        $this->expectException(InvalidSignatureTransitionException::class);

        $this->machine->documentTo(
            $documento,
            SignatureDocument::STATUS_AWAITING_SIGNATURE,
            SignatureAuditEvent::EVENT_FROZEN,
        );
    }

    /**
     * Cancelar um documento já assinado apagaria um ato que aconteceu. O
     * caminho para desfazê-lo é outro documento.
     */
    public function test_documento_assinado_so_pode_ser_finalizado(): void
    {
        $documento = $this->criaDocumentoDeAssinatura();

        $this->machine->documentTo($documento, SignatureDocument::STATUS_AWAITING_SIGNATURE, SignatureAuditEvent::EVENT_FROZEN);
        $this->machine->documentTo($documento, SignatureDocument::STATUS_SIGNED, SignatureAuditEvent::EVENT_SIGNED);

        $this->assertTrue($this->machine->canDocumentGoTo(
            SignatureDocument::STATUS_SIGNED,
            SignatureDocument::STATUS_FINALIZED,
        ));

        $this->expectException(InvalidSignatureTransitionException::class);

        $this->machine->documentTo($documento, SignatureDocument::STATUS_CANCELED, SignatureAuditEvent::EVENT_CANCELED);
    }

    public function test_transicao_recusada_nao_altera_o_status_nem_grava_evento(): void
    {
        $documento = $this->criaDocumentoDeAssinatura();

        $this->machine->documentTo($documento, SignatureDocument::STATUS_CANCELED, SignatureAuditEvent::EVENT_CANCELED);

        $eventosAntes = SignatureAuditEvent::count();

        try {
            $this->machine->documentTo($documento, SignatureDocument::STATUS_SIGNED, SignatureAuditEvent::EVENT_SIGNED);
            $this->fail('A transição inválida deveria ter lançado exceção.');
        } catch (InvalidSignatureTransitionException) {
            // esperado
        }

        $this->assertSame(SignatureDocument::STATUS_CANCELED, $documento->fresh()->status);
        $this->assertSame($eventosAntes, SignatureAuditEvent::count());
    }

    public function test_solicitacao_consumida_nao_e_consumida_de_novo(): void
    {
        $documento = $this->criaDocumentoDeAssinatura();
        $signatario = $documento->signers()->first();

        $solicitacao = SignatureRequest::create([
            'signature_signer_id' => $signatario->id,
            'token_hash' => hash('sha256', 'token-de-teste'),
            'expires_at' => now()->addMinutes(5),
        ]);

        $this->machine->requestTo(
            $solicitacao,
            SignatureRequest::STATUS_CONSUMED,
            SignatureAuditEvent::EVENT_QR_CONSUMED,
            ['consumed_at' => now()],
        );

        $this->assertSame(SignatureRequest::STATUS_CONSUMED, $solicitacao->fresh()->status);

        $this->expectException(InvalidSignatureTransitionException::class);

        $this->machine->requestTo(
            $solicitacao,
            SignatureRequest::STATUS_CONSUMED,
            SignatureAuditEvent::EVENT_QR_CONSUMED,
        );
    }

    /** QR regerado: o anterior vira `superseded`, que é diferente de cancelado. */
    public function test_solicitacao_pendente_pode_ser_substituida(): void
    {
        $documento = $this->criaDocumentoDeAssinatura();
        $signatario = $documento->signers()->first();

        $solicitacao = SignatureRequest::create([
            'signature_signer_id' => $signatario->id,
            'token_hash' => hash('sha256', 'token-antigo'),
            'expires_at' => now()->addMinutes(5),
        ]);

        $this->machine->requestTo(
            $solicitacao,
            SignatureRequest::STATUS_SUPERSEDED,
            SignatureAuditEvent::EVENT_QR_REISSUED,
        );

        $this->assertSame(SignatureRequest::STATUS_SUPERSEDED, $solicitacao->fresh()->status);
        $this->assertFalse($solicitacao->fresh()->isReadable());
    }

    public function test_signatario_que_assinou_nao_recusa_depois(): void
    {
        $documento = $this->criaDocumentoDeAssinatura();
        $signatario = $documento->signers()->first();

        $this->machine->signerTo(
            $signatario,
            SignatureSigner::STATUS_SIGNED,
            SignatureAuditEvent::EVENT_SIGNED,
            ['signed_at' => now()],
        );

        $this->expectException(InvalidSignatureTransitionException::class);

        $this->machine->signerTo(
            $signatario,
            SignatureSigner::STATUS_REFUSED,
            SignatureAuditEvent::EVENT_REFUSED,
        );
    }

    /**
     * A trilha é do DOCUMENTO: um evento de solicitação ou de signatário
     * precisa aparecer na história do documento, senão a auditoria ficaria
     * espalhada em três lugares e ninguém conseguiria lê-la em ordem.
     */
    public function test_evento_de_solicitacao_entra_na_trilha_do_documento(): void
    {
        $documento = $this->criaDocumentoDeAssinatura();
        $signatario = $documento->signers()->first();

        $solicitacao = SignatureRequest::create([
            'signature_signer_id' => $signatario->id,
            'token_hash' => hash('sha256', 'token-de-teste'),
            'expires_at' => now()->addMinutes(5),
        ]);

        $this->machine->requestTo(
            $solicitacao,
            SignatureRequest::STATUS_CANCELED,
            SignatureAuditEvent::EVENT_CANCELED,
        );

        $evento = SignatureAuditEvent::where('signature_document_id', $documento->id)->latest('id')->first();

        $this->assertNotNull($evento);
        $this->assertSame($signatario->id, $evento->signature_signer_id);
        $this->assertSame($solicitacao->id, $evento->signature_request_id);
    }
}
