<?php

namespace Tests\Feature;

use App\Mail\SignatureCopyMail;
use App\Models\SignatureAuditEvent;
use App\Models\SignatureDocument;
use App\Services\Signature\SignatureDocumentRenderer;
use App\Services\Signature\SignatureDocumentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsGovbrSignedPdf;
use Tests\Concerns\CreatesSignatureSchema;
use Tests\Concerns\MocksSignatureUser;
use Tests\TestCase;

/**
 * Fase 4: a finalização do documento assinado pelo gov.br.
 *
 * O final é o arquivo do gov.br, sem tocar; o que seria a página de manifesto
 * sai como relatório de validação, num PDF À PARTE — e segue o final na via
 * por e-mail, no servidor de arquivos e na tela.
 */
class SignatureGovbrFinalizationTest extends TestCase
{
    use BuildsGovbrSignedPdf;
    use CreatesSignatureSchema;
    use MocksSignatureUser;

    /** @var array{cert: string, key: string} */
    private array $ac;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSignatureSchema();
        $this->createPermissionSchema();

        Storage::fake(config('signature.disk'));
        Storage::fake('signature_archive');
        Mail::fake();

        $this->ac = $this->govbrAc();
        $this->govbrConfiaEm($this->ac);
    }

    private function atendente()
    {
        return $this->actingAs($this->usuarioComPermissoes(['assinatura.documentos']));
    }

    /**
     * Um documento de Maria, preparado, convidado e assinado pelo gov.br.
     * A fila é `sync`: o envio aprovado já finaliza.
     *
     * @return array{0: SignatureDocument, 1: string}  o documento e o PDF assinado
     */
    private function assinadoPeloGovbr(): array
    {
        $documento = app(SignatureDocumentService::class)->freeze(
            $this->criaDocumentoDeAssinatura([], ['email' => 'maria@example.com'])
        );

        $this->atendente()->post(route('signature-documents.govbr.prepare', $documento));
        $this->atendente()->post(route('signature-documents.govbr.invite', [$documento, $documento->signers()->first()]), [
            'email' => 'maria@example.com',
        ]);

        $disco = Storage::disk(config('signature.disk'));
        $assinado = $this->govbrAssina(
            $disco->get($documento->fresh()->original_path),
            $this->govbrCertificado($this->ac, '12345678909', 'MARIA DE SOUZA'),
        );

        $this->atendente()->post(route('signature-documents.govbr.store', $documento), [
            'documento' => UploadedFile::fake()->createWithContent('assinado.pdf', $assinado),
        ])->assertSessionHas('success');

        return [$documento->fresh(), $assinado];
    }

    public function test_final_e_o_arquivo_do_gov_br_e_o_relatorio_sai_a_parte(): void
    {
        [$documento, $assinado] = $this->assinadoPeloGovbr();
        $disco = Storage::disk(config('signature.disk'));

        $this->assertSame(SignatureDocument::STATUS_FINALIZED, $documento->status);
        $this->assertSame($assinado, $disco->get($documento->final_path));

        $this->assertNotNull($documento->report_path);
        $relatorio = $disco->get($documento->report_path);
        $this->assertStringStartsWith('%PDF', $relatorio);
        $this->assertSame(hash('sha256', $relatorio), $documento->report_sha256);

        $evento = SignatureAuditEvent::where('event', SignatureAuditEvent::EVENT_FINALIZED)->sole();
        $this->assertSame('gov.br', $evento->payload['origem']);
        $this->assertSame($documento->report_sha256, $evento->payload['relatorio_sha256']);
    }

    public function test_relatorio_traz_os_hashes_a_conferencia_e_o_validador_oficial(): void
    {
        [$documento, $assinado] = $this->assinadoPeloGovbr();
        $maria = $documento->signers()->first();

        $html = app(SignatureDocumentRenderer::class)->govbrReportHtml($documento, hash('sha256', $assinado));

        $this->assertStringContainsString('Relatório de assinatura pelo gov.br', $html);
        $this->assertStringContainsString($documento->original_sha256, $html);
        $this->assertStringContainsString(hash('sha256', $assinado), $html);
        $this->assertStringContainsString('MARIA DE SOUZA', $html);
        $this->assertStringContainsString($maria->maskedCpf(), $html);
        $this->assertStringContainsString('m***a@example.com', $html);
        $this->assertStringContainsString('validar.iti.gov.br', $html);

        // CPF inteiro, nunca.
        $this->assertStringNotContainsString('12345678909', $html);
        $this->assertStringNotContainsString('123.456.789-09', $html);
    }

    public function test_via_por_email_leva_o_pdf_assinado_e_o_relatorio(): void
    {
        [$documento, $assinado] = $this->assinadoPeloGovbr();
        $relatorio = Storage::disk(config('signature.disk'))->get($documento->report_path);

        Mail::assertSent(SignatureCopyMail::class, function (SignatureCopyMail $mail) use ($documento, $assinado, $relatorio) {
            $mail->render();

            return $mail->hasTo('maria@example.com')
                && $mail->hasAttachedData($assinado, 'documento-assinado-' . $documento->validation_code . '.pdf', ['mime' => 'application/pdf'])
                && $mail->hasAttachedData($relatorio, 'relatorio-govbr-' . $documento->validation_code . '.pdf', ['mime' => 'application/pdf']);
        });
    }

    public function test_servidor_de_arquivos_recebe_o_relatorio_ao_lado_do_pdf(): void
    {
        config(['signature.archive.enabled' => true]);

        [$documento, $assinado] = $this->assinadoPeloGovbr();
        $arquivo = Storage::disk('signature_archive');

        $this->assertNotNull($documento->archive_path);
        $this->assertSame($assinado, $arquivo->get($documento->archive_path));

        $caminho = preg_replace('/\.pdf$/', '', $documento->archive_path) . ' - relatorio gov.br.pdf';
        $this->assertSame(Storage::disk(config('signature.disk'))->get($documento->report_path), $arquivo->get($caminho));
    }

    public function test_tela_oferece_o_relatorio_para_baixar(): void
    {
        [$documento] = $this->assinadoPeloGovbr();

        $this->atendente()->get(route('signature-documents.show', $documento))
            ->assertOk()
            ->assertSee('Relatório gov.br');

        $resposta = $this->atendente()->get(route('signature-documents.pdf', [$documento, 'versao' => 'relatorio']))->assertOk();
        $this->assertSame(
            Storage::disk(config('signature.disk'))->get($documento->report_path),
            $resposta->streamedContent(),
        );
    }

    public function test_documento_do_tablet_nao_tem_relatorio(): void
    {
        $documento = app(SignatureDocumentService::class)->freeze($this->criaDocumentoDeAssinatura());

        $this->atendente()->get(route('signature-documents.show', $documento))
            ->assertOk()
            ->assertDontSee('Relatório gov.br');

        $this->atendente()->get(route('signature-documents.pdf', [$documento, 'versao' => 'relatorio']))->assertNotFound();
    }
}
