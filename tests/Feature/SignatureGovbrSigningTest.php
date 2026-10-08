<?php

namespace Tests\Feature;

use App\Mail\SignatureCopyMail;
use App\Models\SignatureAuditEvent;
use App\Models\SignatureDocument;
use App\Models\SignatureGovbrCheck;
use App\Models\SignatureRequest;
use App\Models\SignatureSigner;
use App\Services\Signature\SignatureDocumentService;
use App\Services\Signature\SignatureRequestService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsGovbrSignedPdf;
use Tests\Concerns\CreatesSignatureSchema;
use Tests\Concerns\MocksSignatureUser;
use Tests\TestCase;

/**
 * A assinatura pelo gov.br CONCLUINDO a assinatura no Lara.
 *
 * Preparar o documento → a pessoa assina no gov.br → o atendente envia →
 * aprovado, o signatário passa a assinado e, quando todos assinaram, o arquivo
 * que voltou vira o PDF final, sem tocar.
 *
 * A fila da suíte é `sync`: o envio que fecha o documento já roda a
 * finalização e a via por e-mail, e o teste vê o caminho inteiro.
 */
class SignatureGovbrSigningTest extends TestCase
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
        Mail::fake();

        $this->ac = $this->govbrAc();
        $this->govbrConfiaEm($this->ac);
    }

    /** Documento congelado com Maria (1º) e João (2º, testemunha). */
    private function documentoComDois(): SignatureDocument
    {
        $documento = $this->criaDocumentoDeAssinatura([], ['email' => 'maria@example.com']);

        SignatureSigner::create([
            'signature_document_id' => $documento->id,
            'name' => 'Joao da Silva',
            'cpf' => '52998224725',
            'role' => SignatureSigner::ROLE_WITNESS,
            'position' => 2,
        ]);

        return app(SignatureDocumentService::class)->freeze($documento->fresh());
    }

    private function prepara(SignatureDocument $documento)
    {
        return $this->actingAs($this->usuarioComPermissoes(['assinatura.documentos']))
            ->post(route('signature-documents.govbr.prepare', $documento));
    }

    private function envia(SignatureDocument $documento, string $bytes)
    {
        return $this->actingAs($this->usuarioComPermissoes(['assinatura.documentos']))
            ->post(route('signature-documents.govbr.store', $documento), [
                'documento' => UploadedFile::fake()->createWithContent('assinado.pdf', $bytes),
            ]);
    }

    private function original(SignatureDocument $documento): string
    {
        return Storage::disk(config('signature.disk'))->get($documento->fresh()->original_path);
    }

    private function assinaMaria(string $pdf): string
    {
        return $this->govbrAssina($pdf, $this->govbrCertificado($this->ac, '12345678909', 'MARIA DE SOUZA'));
    }

    private function assinaJoao(string $pdf): string
    {
        return $this->govbrAssina($pdf, $this->govbrCertificado($this->ac, '52998224725', 'JOAO DA SILVA'));
    }

    public function test_preparar_muda_o_prazo_cancela_o_qr_e_tira_o_documento_do_tablet(): void
    {
        $documento = app(SignatureDocumentService::class)->freeze($this->criaDocumentoDeAssinatura());
        $qr = app(SignatureRequestService::class)->issue($documento->signers()->first())['request'];

        $this->prepara($documento)
            ->assertRedirect(route('signature-documents.show', [$documento, 'aba' => 'govbr']))
            ->assertSessionHas('success');

        $documento->refresh();

        $this->assertNotNull($documento->govbr_sent_at);
        $this->assertTrue($documento->expires_at->between(now()->addDays(7)->subMinute(), now()->addDays(7)->addMinute()));
        $this->assertSame(SignatureRequest::STATUS_CANCELED, $qr->fresh()->status);
        $this->assertSame(1, SignatureAuditEvent::where('event', SignatureAuditEvent::EVENT_GOVBR_PREPARED)->count());

        // O tablet não libera mais: um PDF não carrega as duas assinaturas.
        $this->assertStringContainsString('gov.br', (string) $documento->signers()->first()->releaseBlockReason());
    }

    public function test_documento_com_visto_nao_pode_ir_para_o_gov_br(): void
    {
        $modelo = $this->criaModeloDeAssinatura(['requires_initials' => true]);
        $documento = app(SignatureDocumentService::class)
            ->freeze($this->criaDocumentoDeAssinatura(['template' => $modelo]));

        $this->prepara($documento)->assertSessionHas('warning');

        $this->assertNull($documento->fresh()->govbr_sent_at);
    }

    public function test_documento_ja_assinado_no_tablet_nao_vai_para_o_gov_br(): void
    {
        $documento = $this->documentoComDois();
        $documento->signers()->first()->forceFill([
            'status' => SignatureSigner::STATUS_SIGNED,
            'signed_at' => now(),
        ])->save();

        $this->prepara($documento)->assertSessionHas('warning');

        $this->assertNull($documento->fresh()->govbr_sent_at);
    }

    public function test_um_signatario_conclui_finaliza_com_o_arquivo_do_gov_br_e_manda_a_via(): void
    {
        $documento = app(SignatureDocumentService::class)
            ->freeze($this->criaDocumentoDeAssinatura([], ['email' => 'maria@example.com']));
        $this->prepara($documento);

        $assinado = $this->assinaMaria($this->original($documento));

        $this->envia($documento, $assinado)->assertSessionHas('success');

        $documento->refresh();
        $conferencia = SignatureGovbrCheck::sole();
        $maria = $documento->signers()->first();

        $this->assertSame(SignatureSigner::STATUS_SIGNED, $maria->status);
        $this->assertSame($conferencia->id, $maria->govbr_check_id);
        $this->assertNotNull($maria->signed_at);
        $this->assertTrue($conferencia->closedDocument());
        $this->assertSame(['Maria de Souza'], $conferencia->concludedNames());

        // A fila é sync: o documento já foi finalizado — e o final é o arquivo
        // que voltou, byte a byte, sem manifesto nem re-renderização.
        $this->assertSame(SignatureDocument::STATUS_FINALIZED, $documento->status);
        $this->assertSame($conferencia->id, $documento->govbr_check_id);
        $this->assertSame($assinado, Storage::disk(config('signature.disk'))->get($documento->final_path));
        $this->assertSame(hash('sha256', $assinado), $documento->final_sha256);

        Mail::assertSent(SignatureCopyMail::class, fn($mail) => $mail->hasTo('maria@example.com'));

        // A trilha diz por onde a pessoa assinou.
        $assinou = SignatureAuditEvent::where('event', SignatureAuditEvent::EVENT_SIGNED)
            ->whereNotNull('signature_signer_id')->sole();
        $this->assertSame('gov.br', $assinou->payload['via']);
    }

    public function test_dois_signatarios_o_segundo_assina_o_arquivo_do_primeiro(): void
    {
        $documento = $this->documentoComDois();
        $this->prepara($documento);

        $daMaria = $this->assinaMaria($this->original($documento));
        $this->envia($documento, $daMaria)->assertSessionHas('success');

        $documento->refresh();
        $this->assertSame(SignatureDocument::STATUS_AWAITING_SIGNATURE, $documento->status);
        $this->assertSame(
            [SignatureSigner::STATUS_SIGNED, SignatureSigner::STATUS_PENDING],
            $documento->signers()->pluck('status')->all(),
        );

        $dosDois = $this->assinaJoao($daMaria);
        $this->envia($documento, $dosDois)->assertSessionHas('success');

        $documento->refresh();
        $segunda = SignatureGovbrCheck::orderByDesc('id')->first();

        $this->assertSame(SignatureDocument::STATUS_FINALIZED, $documento->status);
        $this->assertSame($segunda->id, $documento->govbr_check_id);
        $this->assertSame(['Joao da Silva'], $segunda->concludedNames());
        $this->assertSame($dosDois, Storage::disk(config('signature.disk'))->get($documento->final_path));
    }

    public function test_segundo_signatario_que_assina_o_original_nao_conclui(): void
    {
        $documento = $this->documentoComDois();
        $this->prepara($documento);

        $original = $this->original($documento);
        $this->envia($documento, $this->assinaMaria($original));

        // João assinou o ORIGINAL, e não o arquivo com a assinatura da Maria:
        // o arquivo é válido, mas não pode virar o final.
        $this->envia($documento, $this->assinaJoao($original))->assertSessionHas('warning');

        $conferencia = SignatureGovbrCheck::orderByDesc('id')->first();

        $this->assertTrue($conferencia->valid);
        $this->assertStringContainsString('Maria de Souza', (string) $conferencia->conclusionReason());
        $this->assertSame(SignatureSigner::STATUS_PENDING, $documento->signers()->where('position', 2)->value('status'));
    }

    public function test_a_ordem_e_livre_o_segundo_pode_assinar_antes(): void
    {
        $documento = $this->documentoComDois();
        $this->prepara($documento);

        // João (2º da lista) assina primeiro.
        $doJoao = $this->assinaJoao($this->original($documento));
        $this->envia($documento, $doJoao)->assertSessionHas('success');

        $this->assertSame(['Joao da Silva'], SignatureGovbrCheck::sole()->concludedNames());
        $this->assertSame(SignatureDocument::STATUS_AWAITING_SIGNATURE, $documento->fresh()->status);

        // Maria assina depois, sobre o arquivo do João: fecha.
        $dosDois = $this->assinaMaria($doJoao);
        $this->envia($documento, $dosDois)->assertSessionHas('success');

        $this->assertSame(SignatureDocument::STATUS_FINALIZED, $documento->fresh()->status);
        $this->assertSame($dosDois, Storage::disk(config('signature.disk'))->get($documento->fresh()->final_path));
    }

    public function test_reenviar_o_mesmo_arquivo_nao_assina_de_novo(): void
    {
        $documento = $this->documentoComDois();
        $this->prepara($documento);

        $daMaria = $this->assinaMaria($this->original($documento));
        $this->envia($documento, $daMaria);
        $this->envia($documento, $daMaria)->assertSessionHas('warning');

        $this->assertStringContainsString('Nenhuma assinatura nova', (string) SignatureGovbrCheck::orderByDesc('id')->first()->conclusionReason());
        $this->assertSame(1, SignatureAuditEvent::where('event', SignatureAuditEvent::EVENT_SIGNED)->whereNotNull('signature_signer_id')->count());
    }

    public function test_sem_preparar_a_conferencia_nao_conclui(): void
    {
        $documento = app(SignatureDocumentService::class)->freeze($this->criaDocumentoDeAssinatura());

        $this->envia($documento, $this->assinaMaria($this->original($documento)))->assertSessionHas('warning');

        $this->assertStringContainsString('preparado', (string) SignatureGovbrCheck::sole()->conclusionReason());
        $this->assertSame(SignatureDocument::STATUS_AWAITING_SIGNATURE, $documento->fresh()->status);
    }

    public function test_telas_mostram_que_a_assinatura_foi_pelo_gov_br(): void
    {
        $documento = app(SignatureDocumentService::class)->freeze($this->criaDocumentoDeAssinatura());
        $this->prepara($documento);
        $this->envia($documento, $this->assinaMaria($this->original($documento)));

        $this->actingAs($this->usuarioComPermissoes(['assinatura.documentos']))
            ->get(route('signature-documents.show', $documento))
            ->assertOk()
            ->assertSee('pelo gov.br');

        $this->actingAs($this->usuarioComPermissoes(['assinatura.documentos']))
            ->get(route('signature-documents.show', [$documento, 'aba' => 'govbr']))
            ->assertOk()
            ->assertSee('Registrou a assinatura de')
            ->assertSee('este arquivo é o PDF final');

        $this->get(route('signature.validate', $documento->fresh()->validation_code))
            ->assertOk()
            ->assertSee('pelo gov.br');
    }

    public function test_prazo_vencido_nao_conclui(): void
    {
        $documento = $this->documentoComDois();
        $this->prepara($documento);

        $assinado = $this->assinaMaria($this->original($documento));

        // O prazo do gov.br passou e o agendador encerrou o documento.
        $documento->fresh()->forceFill(['expires_at' => now()->subMinute()])->save();
        $this->artisan('signature:expire')->assertSuccessful();

        $this->envia($documento, $assinado)->assertSessionHas('warning');

        $this->assertSame(SignatureDocument::STATUS_EXPIRED, $documento->fresh()->status);
        $this->assertSame(0, $documento->signers()->where('status', SignatureSigner::STATUS_SIGNED)->count());
        $this->assertNotNull(SignatureGovbrCheck::sole()->conclusionReason());
    }
}
