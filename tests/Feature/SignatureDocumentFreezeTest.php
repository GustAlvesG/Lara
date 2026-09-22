<?php

namespace Tests\Feature;

use App\Exceptions\SignatureDocumentLockedException;
use App\Models\SignatureAuditEvent;
use App\Models\SignatureDocument;
use App\Models\SignatureSigner;
use App\Services\Signature\SignatureDocumentService;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesSignatureSchema;
use Tests\TestCase;

/**
 * O congelamento — o ato que separa o rascunho do documento com valor.
 *
 * Depois dele, o texto não é mais o do modelo (é o `body_snapshot`), o PDF
 * existe, o hash existe, e nada disso muda. É o que sustenta a promessa da
 * página de validação: "este arquivo é o que foi assinado".
 *
 * Sem `RefreshDatabase` e sem tocar no model `User` — ver CreatesSignatureSchema.
 */
class SignatureDocumentFreezeTest extends TestCase
{
    use CreatesSignatureSchema;

    private SignatureDocumentService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSignatureSchema();

        Storage::fake(config('signature.disk'));

        $this->service = app(SignatureDocumentService::class);
    }

    public function test_congelar_gera_pdf_hash_e_codigo_de_validacao(): void
    {
        $documento = $this->criaDocumentoDeAssinatura();

        $congelado = $this->service->freeze($documento, userId: 7);

        $this->assertSame(SignatureDocument::STATUS_AWAITING_SIGNATURE, $congelado->status);
        $this->assertNotNull($congelado->frozen_at);
        $this->assertNotNull($congelado->original_path);
        $this->assertSame(64, strlen($congelado->original_sha256));
        $this->assertSame(12, strlen($congelado->validation_code));

        Storage::disk(config('signature.disk'))->assertExists($congelado->original_path);

        // O hash é do ARQUIVO gravado, não de outra coisa qualquer.
        $this->assertSame(
            hash('sha256', Storage::disk(config('signature.disk'))->get($congelado->original_path)),
            $congelado->original_sha256,
        );

        $this->assertDatabaseHas('signature_audit_events', [
            'signature_document_id' => $documento->id,
            'event' => SignatureAuditEvent::EVENT_FROZEN,
        ]);
    }

    public function test_pdf_sai_com_o_documento_e_o_signatario(): void
    {
        $documento = $this->criaDocumentoDeAssinatura();

        $congelado = $this->service->freeze($documento);

        $pdf = Storage::disk(config('signature.disk'))->get($congelado->original_path);

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertGreaterThan(1000, strlen($pdf));
    }

    public function test_documento_congelado_nao_pode_mais_ser_editado(): void
    {
        $documento = $this->criaDocumentoDeAssinatura();

        $this->service->freeze($documento);

        $this->expectException(SignatureDocumentLockedException::class);
        $this->expectExceptionMessage('já congelado');

        $this->service->update($documento->fresh(), ['title' => 'Outro título'], []);
    }

    public function test_documento_nao_congela_duas_vezes(): void
    {
        $documento = $this->criaDocumentoDeAssinatura();

        $congelado = $this->service->freeze($documento);
        $hashOriginal = $congelado->original_sha256;

        try {
            $this->service->freeze($congelado->fresh());
            $this->fail('Congelar de novo deveria ter sido recusado.');
        } catch (SignatureDocumentLockedException) {
            // esperado
        }

        $this->assertSame($hashOriginal, $congelado->fresh()->original_sha256);
    }

    public function test_documento_sem_signatario_nao_congela(): void
    {
        $modelo = $this->criaModeloDeAssinatura();

        $documento = SignatureDocument::create([
            'signature_template_id' => $modelo->id,
            'template_version' => $modelo->version,
            'title' => 'Termo sem signatário',
            'created_by' => 1,
        ]);

        $this->expectException(SignatureDocumentLockedException::class);
        $this->expectExceptionMessage('sem signatário');

        $this->service->freeze($documento);
    }

    public function test_variavel_obrigatoria_em_branco_impede_o_congelamento(): void
    {
        $modelo = $this->criaModeloDeAssinatura([
            'body_html' => '<p>Espaço reservado: [[espaco]].</p>[[assinatura]]',
            'variables' => [['key' => 'espaco', 'label' => 'Espaço', 'required' => true]],
        ]);

        $documento = $this->criaDocumentoDeAssinatura(['template' => $modelo, 'data' => []]);

        $this->expectException(SignatureDocumentLockedException::class);
        $this->expectExceptionMessage('Espaço');

        $this->service->freeze($documento);
    }

    public function test_variavel_preenchida_entra_no_corpo_congelado(): void
    {
        $modelo = $this->criaModeloDeAssinatura([
            'body_html' => '<p>Espaço reservado: [[espaco]].</p>[[assinatura]]',
            'variables' => [['key' => 'espaco', 'label' => 'Espaço', 'required' => true]],
        ]);

        $documento = $this->criaDocumentoDeAssinatura([
            'template' => $modelo,
            'data' => ['espaco' => 'Salão de festas'],
        ]);

        $congelado = $this->service->freeze($documento);

        $this->assertStringContainsString('Salão de festas', $congelado->body_snapshot);
        $this->assertStringNotContainsString('[[espaco]]', $congelado->body_snapshot);
    }

    /**
     * A garantia central do versionamento: revisar o modelo depois não
     * reescreve o que já foi congelado.
     */
    public function test_revisar_o_modelo_nao_altera_documento_ja_congelado(): void
    {
        $modelo = $this->criaModeloDeAssinatura([
            'body_html' => '<p>Texto da versão um.</p>[[assinatura]]',
        ]);

        $documento = $this->criaDocumentoDeAssinatura(['template' => $modelo]);
        $congelado = $this->service->freeze($documento);

        $hashAntes = $congelado->original_sha256;
        $corpoAntes = $congelado->body_snapshot;

        $modelo->newVersion(['body_html' => '<p>Texto da versão dois.</p>[[assinatura]]'], userId: 1);

        $recarregado = $congelado->fresh();

        $this->assertSame($hashAntes, $recarregado->original_sha256);
        $this->assertSame($corpoAntes, $recarregado->body_snapshot);
        $this->assertStringContainsString('versão um', $recarregado->body_snapshot);
        $this->assertSame(1, $recarregado->template_version);
    }

    public function test_cancelar_derruba_os_signatarios_pendentes(): void
    {
        $documento = $this->criaDocumentoDeAssinatura();
        $this->service->freeze($documento);

        $cancelado = $this->service->cancel($documento->fresh(), 'Associado desistiu', userId: 7);

        $this->assertSame(SignatureDocument::STATUS_CANCELED, $cancelado->status);
        $this->assertSame('Associado desistiu', $cancelado->canceled_reason);
        $this->assertSame(
            SignatureSigner::STATUS_CANCELED,
            $documento->signers()->first()->status,
        );
    }

    public function test_codigo_de_validacao_nao_usa_caracteres_ambiguos(): void
    {
        $congelado = $this->service->freeze($this->criaDocumentoDeAssinatura());

        // 0/O e 1/I/L não entram: o código é ditado por telefone.
        $this->assertSame(1, preg_match('/^[23456789ABCDEFGHJKMNPQRSTUVWXYZ]{12}$/', $congelado->validation_code));
    }
}
