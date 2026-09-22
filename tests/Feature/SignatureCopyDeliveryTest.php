<?php

namespace Tests\Feature;

use App\Jobs\SendSignatureCopy;
use App\Mail\SignatureCopyMail;
use App\Models\SignatureAuditEvent;
use App\Models\SignatureDocument;
use App\Models\SignatureEvidence;
use App\Models\SignatureSigner;
use App\Services\Signature\SignatureDocumentService;
use App\Services\Signature\SignatureStateMachine;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesSignatureSchema;
use Tests\TestCase;

/**
 * O envio da via assinada ao próprio signatário.
 *
 * Três condições, todas necessárias: a pessoa PEDIU no tablet, há e-mail
 * cadastrado, e o documento está finalizado — é o PDF final, com o manifesto,
 * que se envia.
 *
 * O WhatsApp não entra nesta entrega. O gateway da Poli responde 200 para
 * envios que não entrega, e gravar "enviado" com base nisso colocaria uma
 * informação falsa na trilha de auditoria.
 */
class SignatureCopyDeliveryTest extends TestCase
{
    use CreatesSignatureSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSignatureSchema();

        Storage::fake(config('signature.disk'));
        Mail::fake();
    }

    /** Documento finalizado, com o PDF final no disco. */
    private function documentoFinalizado(array $signerAttributes = []): SignatureDocument
    {
        $documento = app(SignatureDocumentService::class)->freeze(
            $this->criaDocumentoDeAssinatura([], $signerAttributes),
        );

        $signatario = $documento->signers()->first();
        $states = app(SignatureStateMachine::class);

        SignatureEvidence::create([
            'signature_signer_id' => $signatario->id,
            'signature_path' => 'signature/signatures/x.png',
            'accepted' => true,
            'server_signed_at' => now(),
        ]);

        $states->signerTo(
            $signatario,
            SignatureSigner::STATUS_SIGNED,
            SignatureAuditEvent::EVENT_SIGNED,
            ['signed_at' => now()],
        );

        $states->documentTo($documento, SignatureDocument::STATUS_SIGNED, SignatureAuditEvent::EVENT_SIGNED);

        Storage::disk(config('signature.disk'))->put('signature/documents/' . $documento->id . '/final.pdf', '%PDF-final');

        $states->documentTo(
            $documento,
            SignatureDocument::STATUS_FINALIZED,
            SignatureAuditEvent::EVENT_FINALIZED,
            [
                'final_path' => 'signature/documents/' . $documento->id . '/final.pdf',
                'final_sha256' => hash('sha256', '%PDF-final'),
                'finalized_at' => now(),
            ],
        );

        return $documento->fresh();
    }

    public function test_via_e_enviada_a_quem_pediu(): void
    {
        $documento = $this->documentoFinalizado([
            'email' => 'maria@exemplo.com',
            'wants_copy' => true,
        ]);

        $signatario = $documento->signers()->first();

        (new SendSignatureCopy($signatario->id))->handle(app(SignatureStateMachine::class));

        Mail::assertSent(SignatureCopyMail::class, fn($mail) => $mail->hasTo('maria@exemplo.com'));

        $this->assertNotNull($signatario->fresh()->copy_sent_at);

        $this->assertDatabaseHas('signature_audit_events', [
            'signature_document_id' => $documento->id,
            'event' => SignatureAuditEvent::EVENT_COPY_SENT,
        ]);
    }

    public function test_quem_nao_pediu_nao_recebe(): void
    {
        $documento = $this->documentoFinalizado([
            'email' => 'maria@exemplo.com',
            'wants_copy' => false,
        ]);

        (new SendSignatureCopy($documento->signers()->first()->id))->handle(app(SignatureStateMachine::class));

        Mail::assertNothingSent();
    }

    public function test_sem_email_nao_envia(): void
    {
        $documento = $this->documentoFinalizado(['wants_copy' => true]);

        (new SendSignatureCopy($documento->signers()->first()->id))->handle(app(SignatureStateMachine::class));

        Mail::assertNothingSent();
    }

    /** Reentrega da fila não pode mandar o mesmo arquivo duas vezes. */
    public function test_envio_e_idempotente(): void
    {
        $documento = $this->documentoFinalizado([
            'email' => 'maria@exemplo.com',
            'wants_copy' => true,
        ]);

        $signatario = $documento->signers()->first();

        (new SendSignatureCopy($signatario->id))->handle(app(SignatureStateMachine::class));
        (new SendSignatureCopy($signatario->id))->handle(app(SignatureStateMachine::class));

        Mail::assertSentCount(1);
    }

    public function test_documento_nao_finalizado_nao_tem_via_enviada(): void
    {
        $documento = app(SignatureDocumentService::class)->freeze(
            $this->criaDocumentoDeAssinatura([], ['email' => 'maria@exemplo.com', 'wants_copy' => true]),
        );

        (new SendSignatureCopy($documento->signers()->first()->id))->handle(app(SignatureStateMachine::class));

        Mail::assertNothingSent();
    }

    public function test_envio_desligado_por_configuracao_nao_manda(): void
    {
        config(['signature.delivery.email' => false]);

        $documento = $this->documentoFinalizado([
            'email' => 'maria@exemplo.com',
            'wants_copy' => true,
        ]);

        (new SendSignatureCopy($documento->signers()->first()->id))->handle(app(SignatureStateMachine::class));

        Mail::assertNothingSent();
    }

    /**
     * O e-mail leva o PDF final e o código de validação — é o que permite à
     * pessoa conferir a autenticidade meses depois, sem precisar do clube.
     */
    public function test_email_leva_o_pdf_e_o_codigo_de_validacao(): void
    {
        $documento = $this->documentoFinalizado([
            'email' => 'maria@exemplo.com',
            'wants_copy' => true,
        ]);

        $signatario = $documento->signers()->first();

        $mail = new SignatureCopyMail($signatario->fresh());

        $anexos = $mail->attachments();

        $this->assertCount(1, $anexos);
        $this->assertStringContainsString($documento->validation_code, $anexos[0]->as);

        $corpo = $mail->render();

        $this->assertStringContainsString($documento->validation_code, $corpo);
        $this->assertStringContainsString('/validar/' . $documento->validation_code, $corpo);
        // Sem dado pessoal no corpo: e-mail é reencaminhado com facilidade.
        $this->assertStringNotContainsString('12345678909', $corpo);
    }
}
