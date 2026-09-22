<?php

namespace Tests\Feature;

use App\Jobs\FinalizeSignatureDocument;
use App\Models\SignatureAuditEvent;
use App\Models\SignatureDocument;
use App\Models\SignatureEvidence;
use App\Models\SignatureSigner;
use App\Services\Signature\SignatureDocumentService;
use App\Services\Signature\SignatureStateMachine;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesSignatureSchema;
use Tests\TestCase;

/**
 * A finalização: o PDF de entrega, com a página de manifesto.
 *
 * O final é RE-RENDERIZADO a partir do mesmo conteúdo congelado, e não
 * carimbado sobre o original — o dompdf não edita PDF pronto. Por isso o
 * `original_sha256`, gravado antes de qualquer assinatura, é o que amarra "o
 * que foi lido": ele é impresso no manifesto e conferido na página pública.
 */
class SignatureFinalizationTest extends TestCase
{
    use CreatesSignatureSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSignatureSchema();

        Storage::fake(config('signature.disk'));
    }

    /** Documento com tudo assinado, pronto para o job. */
    private function documentoAssinado(): SignatureDocument
    {
        $documento = app(SignatureDocumentService::class)->freeze($this->criaDocumentoDeAssinatura());

        $signatario = $documento->signers()->first();
        $states = app(SignatureStateMachine::class);

        $caminho = 'signature/signatures/' . $documento->id . '/signer_' . $signatario->id . '.png';

        Storage::disk(config('signature.disk'))->put($caminho, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        ));

        SignatureEvidence::create([
            'signature_signer_id' => $signatario->id,
            'signature_path' => $caminho,
            'strokes' => [['points' => array_fill(0, 40, ['x' => 1, 'y' => 2, 't' => 3])]],
            'ip' => '10.0.0.15',
            'user_agent' => 'Tablet do balcão',
            'read_seconds' => 64,
            'scrolled_to_end' => true,
            'accepted' => true,
            'server_signed_at' => now(),
        ]);

        $states->signerTo(
            $signatario,
            SignatureSigner::STATUS_SIGNED,
            SignatureAuditEvent::EVENT_SIGNED,
            ['signed_at' => now()],
        );

        $states->documentTo(
            $documento,
            SignatureDocument::STATUS_SIGNED,
            SignatureAuditEvent::EVENT_SIGNED,
        );

        return $documento->fresh();
    }

    public function test_job_gera_o_pdf_final_e_grava_o_hash(): void
    {
        $documento = $this->documentoAssinado();

        (new FinalizeSignatureDocument($documento->id))->handle(
            app(\App\Services\Signature\SignatureDocumentRenderer::class),
            app(SignatureStateMachine::class),
            app(\App\Services\Signature\SignaturePdfSealer::class),
        );

        $documento->refresh();

        $this->assertSame(SignatureDocument::STATUS_FINALIZED, $documento->status);
        $this->assertNotNull($documento->finalized_at);
        $this->assertSame(64, strlen($documento->final_sha256));

        Storage::disk(config('signature.disk'))->assertExists($documento->final_path);

        $pdf = Storage::disk(config('signature.disk'))->get($documento->final_path);

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertSame(hash('sha256', $pdf), $documento->final_sha256);

        // O final é outro arquivo: tem a assinatura e o manifesto.
        $this->assertNotSame($documento->original_sha256, $documento->final_sha256);

        $this->assertDatabaseHas('signature_audit_events', [
            'signature_document_id' => $documento->id,
            'event' => SignatureAuditEvent::EVENT_FINALIZED,
        ]);
    }

    /**
     * O manifesto precisa conter o hash do ORIGINAL: é ele que amarra o PDF
     * final ao arquivo que a pessoa de fato leu no tablet.
     */
    public function test_manifesto_cita_o_hash_do_original_e_o_codigo_de_validacao(): void
    {
        $documento = $this->documentoAssinado();

        $html = app(\App\Services\Signature\SignatureDocumentRenderer::class)
            ->html($documento, \App\Services\Signature\SignatureDocumentRenderer::MODE_FINAL);

        $this->assertStringContainsString('Manifesto de assinatura eletrônica', $html);
        $this->assertStringContainsString($documento->original_sha256, $html);
        $this->assertStringContainsString($documento->validation_code, $html);
        $this->assertStringContainsString('/validar/' . $documento->validation_code, $html);

        // QR de validação embutido, gerado no servidor.
        $this->assertStringContainsString('data:image/svg+xml;base64,', $html);

        // CPF mascarado, nunca inteiro.
        $this->assertStringContainsString('123.***.**9-09', $html);
        $this->assertStringNotContainsString('12345678909', $html);
    }

    /**
     * Reentrega da fila depois de um timeout: refazer geraria um segundo
     * arquivo e um segundo hash para o mesmo documento.
     */
    public function test_job_e_idempotente(): void
    {
        $documento = $this->documentoAssinado();

        $executa = fn() => (new FinalizeSignatureDocument($documento->id))->handle(
            app(\App\Services\Signature\SignatureDocumentRenderer::class),
            app(SignatureStateMachine::class),
            app(\App\Services\Signature\SignaturePdfSealer::class),
        );

        $executa();
        $hash = $documento->fresh()->final_sha256;
        $eventos = SignatureAuditEvent::where('event', SignatureAuditEvent::EVENT_FINALIZED)->count();

        $executa();

        $this->assertSame($hash, $documento->fresh()->final_sha256);
        $this->assertSame($eventos, SignatureAuditEvent::where('event', SignatureAuditEvent::EVENT_FINALIZED)->count());
    }

    public function test_job_ignora_documento_que_nao_esta_assinado(): void
    {
        $documento = app(SignatureDocumentService::class)->freeze($this->criaDocumentoDeAssinatura());

        (new FinalizeSignatureDocument($documento->id))->handle(
            app(\App\Services\Signature\SignatureDocumentRenderer::class),
            app(SignatureStateMachine::class),
            app(\App\Services\Signature\SignaturePdfSealer::class),
        );

        $this->assertSame(SignatureDocument::STATUS_AWAITING_SIGNATURE, $documento->fresh()->status);
        $this->assertNull($documento->fresh()->final_path);
    }

    /**
     * A falha não perde evidência: assinatura, traço, foto e trilha já estão
     * gravados. O que falta é só o arquivo de entrega, que pode ser refeito.
     */
    public function test_falha_na_finalizacao_vira_evento_e_mantem_o_documento_assinado(): void
    {
        $documento = $this->documentoAssinado();

        (new FinalizeSignatureDocument($documento->id))
            ->failed(new \RuntimeException('disco cheio'));

        $documento->refresh();

        $this->assertSame(SignatureDocument::STATUS_SIGNED, $documento->status);
        $this->assertNotNull($documento->signers()->first()->evidence);

        $evento = SignatureAuditEvent::where('signature_document_id', $documento->id)
            ->where('event', SignatureAuditEvent::EVENT_FINALIZATION_FAILED)
            ->first();

        $this->assertNotNull($evento);
        $this->assertStringContainsString('disco cheio', $evento->payload['erro']);
    }

    /**
     * O lacre PAdES não está implementado. Ligá-lo sem implementar falha alto,
     * na hora — um lacre que silenciosamente não acontece é pior que nenhum,
     * porque alguém passa a contar com ele.
     */
    public function test_lacre_pades_ligado_sem_implementacao_falha_alto(): void
    {
        config(['signature.pades.enabled' => true]);

        $documento = $this->documentoAssinado();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ainda não foi implementado');

        (new FinalizeSignatureDocument($documento->id))->handle(
            app(\App\Services\Signature\SignatureDocumentRenderer::class),
            app(SignatureStateMachine::class),
            app(\App\Services\Signature\SignaturePdfSealer::class),
        );
    }
}
