<?php

namespace Tests\Feature;

use App\Jobs\FinalizeSignatureDocument;
use App\Models\SignatureAttachment;
use App\Models\SignatureAuditEvent;
use App\Models\SignatureDocument;
use App\Models\SignatureSigner;
use App\Models\SignatureTemplate;
use App\Services\Signature\SignatureArchiver;
use App\Services\Signature\SignatureAttachmentService;
use App\Services\Signature\SignatureDocumentRenderer;
use App\Services\Signature\SignatureDocumentService;
use App\Services\Signature\SignatureStateMachine;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesSignatureSchema;
use Tests\Concerns\MocksSignatureUser;
use Tests\TestCase;

/**
 * Anexos do documento — identidade, comprovante.
 *
 * Pedidos como as perguntas: no modelo e no próprio documento, obrigatórios ou
 * opcionais. O atendente envia na tela do documento. O obrigatório trava o
 * congelamento; depois do congelamento o anexo não sai mais; o manifesto e o
 * servidor de arquivos levam o hash e o arquivo.
 */
class SignatureAttachmentTest extends TestCase
{
    use CreatesSignatureSchema;
    use MocksSignatureUser;

    private const PDF = "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n";

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSignatureSchema();
        $this->createPermissionSchema();

        Storage::fake(config('signature.disk'));
        Storage::fake('signature_archive');
        Queue::fake();
    }

    private function atendente()
    {
        return $this->actingAs($this->usuarioComPermissoes(['assinatura.documentos']));
    }

    /** Modelo que pede identidade (obrigatória) e comprovante (opcional). */
    private function modeloComAnexos(): SignatureTemplate
    {
        return $this->criaModeloDeAssinatura([
            'attachments' => SignatureAttachmentService::normalize([
                ['label' => 'Documento de identidade', 'required' => true],
                ['label' => 'Comprovante de residência', 'required' => false],
            ], 'mod'),
        ]);
    }

    private function envia(SignatureDocument $documento, ?string $item, $arquivo, ?string $rotulo = null)
    {
        return $this->atendente()->post(route('signature-documents.attachments.store', $documento), array_filter([
            'item' => $item,
            'rotulo' => $rotulo,
            'arquivo' => $arquivo,
        ]));
    }

    private function pdf(string $nome = 'rg.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nome, self::PDF);
    }

    public function test_modelo_grava_os_anexos_pedidos_e_a_revisao_os_mantem(): void
    {
        $this->actingAs($this->usuarioComPermissoes(['assinatura.modelos']))
            ->post(route('signature-templates.store'), [
                'name' => 'Cadastro de sócio',
                'body_html' => '<p>Texto</p>[[assinatura]]',
                'identity_check' => SignatureTemplate::IDENTITY_PARTIAL,
                'attachments' => [
                    ['label' => 'Documento de identidade', 'required' => '1'],
                    ['label' => '  ', 'required' => '1'],
                    ['label' => 'Comprovante de residência', 'required' => '0'],
                ],
            ])
            ->assertSessionHasNoErrors();

        $modelo = SignatureTemplate::firstOrFail();

        $this->assertSame([
            ['key' => 'mod_documento_de_identidade', 'label' => 'Documento de identidade', 'required' => true],
            ['key' => 'mod_comprovante_de_residencia', 'label' => 'Comprovante de residência', 'required' => false],
        ], $modelo->declaredAttachments());

        $nova = $modelo->newVersion(['name' => 'Cadastro de sócio v2']);
        $this->assertSame($modelo->declaredAttachments(), $nova->declaredAttachments());
    }

    public function test_formularios_mostram_a_lista_de_anexos(): void
    {
        $modelo = $this->modeloComAnexos();

        $this->actingAs($this->usuarioComPermissoes(['assinatura.modelos']))
            ->get(route('signature-templates.edit', $modelo))
            ->assertOk()
            ->assertSee('Anexos pedidos')
            ->assertSee('Documento de identidade');

        $this->actingAs($this->usuarioComPermissoes(['assinatura.modelos']))
            ->get(route('signature-templates.show', $modelo))
            ->assertOk()
            ->assertSee('Comprovante de residência');

        $this->atendente()->get(route('signature-documents.create', ['template' => $modelo->id]))
            ->assertOk()
            ->assertSee('O modelo pede:')
            ->assertSee('Documento de identidade (obrigatório)');

        $documento = $this->criaDocumentoDeAssinatura([
            'template' => $modelo,
            'attachment_requirements' => SignatureAttachmentService::normalize([['label' => 'Laudo médico']], 'doc'),
        ]);

        // Os itens vão para o JavaScript da lista, em JSON (o "é" sai escapado).
        $this->atendente()->get(route('signature-documents.edit', $documento))
            ->assertOk()
            ->assertSee(json_encode('Laudo médico', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT), false);
    }

    public function test_documento_pede_anexos_proprios_alem_dos_do_modelo(): void
    {
        $modelo = $this->modeloComAnexos();

        $this->atendente()->post(route('signature-documents.store'), [
            'signature_template_id' => $modelo->id,
            'signers' => [['name' => 'Maria de Souza', 'cpf' => '123.456.789-09']],
            'attachments' => [['label' => 'Laudo médico', 'required' => '1']],
        ])->assertRedirect();

        $documento = SignatureDocument::latest('id')->firstOrFail();

        $this->assertSame(
            ['mod_documento_de_identidade', 'mod_comprovante_de_residencia', 'doc_laudo_medico'],
            array_column($documento->attachmentRequirements(), 'key'),
        );
        $this->assertSame(['Documento de identidade', 'Laudo médico'], $documento->missingAttachments());

        $this->atendente()->get(route('signature-documents.show', $documento))
            ->assertOk()
            ->assertSee('Laudo médico')
            ->assertSee('pedido neste documento')
            ->assertSee('pedido pelo modelo')
            ->assertSee('Falta');
    }

    public function test_obrigatorio_nao_trava_o_congelamento(): void
    {
        $documento = $this->criaDocumentoDeAssinatura(['template' => $this->modeloComAnexos()]);

        $this->atendente()->post(route('signature-documents.freeze', $documento))->assertSessionHas('success');

        $this->assertSame(SignatureDocument::STATUS_AWAITING_SIGNATURE, $documento->fresh()->status);
    }

    /**
     * Roda a finalização AGORA. `dispatchSync` não serve: com a fila falsa ele
     * é só registrado, e o job não roda.
     */
    private function finaliza(SignatureDocument $documento): void
    {
        app()->call([new FinalizeSignatureDocument($documento->id), 'handle']);
    }

    /** Congelado e com todos assinados — sem passar pela finalização. */
    private function todosAssinaram(SignatureDocument $documento): SignatureDocument
    {
        $documento = app(SignatureDocumentService::class)->freeze($documento->fresh());
        $estados = app(SignatureStateMachine::class);

        foreach ($documento->signers()->get() as $signatario) {
            $estados->signerTo($signatario, SignatureSigner::STATUS_SIGNED, SignatureAuditEvent::EVENT_SIGNED, ['signed_at' => now()]);
        }

        return $estados->documentTo($documento, SignatureDocument::STATUS_SIGNED, SignatureAuditEvent::EVENT_SIGNED);
    }

    public function test_conclusao_espera_o_obrigatorio_e_o_envio_que_completa_finaliza(): void
    {
        $documento = $this->todosAssinaram($this->criaDocumentoDeAssinatura(['template' => $this->modeloComAnexos()]));

        // Sem a identidade, a finalização não conclui: fica "Assinado", à espera.
        $this->finaliza($documento);
        $this->assertSame(SignatureDocument::STATUS_SIGNED, $documento->fresh()->status);
        $this->assertNull($documento->fresh()->final_path);

        $this->atendente()->get(route('signature-documents.show', $documento))
            ->assertOk()
            ->assertSee('Para concluir, falta enviar:', false)
            ->assertSee('Documento de identidade');

        // Assinado ainda recebe anexo. O opcional não segura nada; o obrigatório que faltava despacha a finalização.
        $this->envia($documento, 'mod_comprovante_de_residencia', $this->pdf('conta.pdf'))->assertSessionHas('success');
        Queue::assertNotPushed(FinalizeSignatureDocument::class);

        $this->envia($documento, 'mod_documento_de_identidade', $this->pdf())->assertSessionHas('success');
        Queue::assertPushed(FinalizeSignatureDocument::class, fn($job) => $job->documentId === $documento->id);

        $this->finaliza($documento);
        $this->assertSame(SignatureDocument::STATUS_FINALIZED, $documento->fresh()->status);
    }

    public function test_envio_guarda_o_arquivo_com_hash_e_a_trilha_nao_leva_o_nome(): void
    {
        $documento = $this->criaDocumentoDeAssinatura(['template' => $this->modeloComAnexos()]);

        $this->envia($documento, 'mod_documento_de_identidade', $this->pdf('RG Maria de Souza.pdf'))
            ->assertSessionHas('success');

        $anexo = SignatureAttachment::sole();
        $this->assertSame('application/pdf', $anexo->mime);
        $this->assertSame(hash('sha256', self::PDF), $anexo->sha256);
        $this->assertSame('Documento de identidade', $anexo->label);
        $this->assertSame('Atendente de Teste', $anexo->uploaded_by_name);
        $this->assertSame(self::PDF, Storage::disk(config('signature.disk'))->get($anexo->path));

        $evento = SignatureAuditEvent::where('event', SignatureAuditEvent::EVENT_ATTACHMENT_ADDED)->sole();
        $this->assertSame($anexo->sha256, $evento->payload['sha256']);
        $this->assertStringNotContainsString('Maria', json_encode($evento->payload));
    }

    public function test_imagem_jpg_e_aceita_e_texto_disfarcado_de_pdf_nao(): void
    {
        $documento = $this->criaDocumentoDeAssinatura(['template' => $this->modeloComAnexos()]);

        $this->envia($documento, 'mod_documento_de_identidade', UploadedFile::fake()->image('rg.jpg'))
            ->assertSessionHas('success');
        $this->assertSame('image/jpeg', SignatureAttachment::sole()->mime);

        // A extensão diz PDF, o conteúdo não: vale o conteúdo.
        $this->envia($documento, 'mod_comprovante_de_residencia', UploadedFile::fake()->createWithContent('conta.pdf', 'só texto'))
            ->assertSessionHas('warning', 'Envie PDF, JPG ou PNG.');

        $this->assertSame(1, SignatureAttachment::count());
    }

    public function test_item_que_o_documento_nao_pede_e_recusado_e_avulso_precisa_de_nome(): void
    {
        $documento = $this->criaDocumentoDeAssinatura();

        $this->envia($documento, 'mod_inventado', $this->pdf())->assertSessionHas('warning');
        $this->envia($documento, null, $this->pdf())->assertSessionHas('warning', 'Diga o que é o anexo.');
        $this->assertSame(0, SignatureAttachment::count());

        $this->envia($documento, null, $this->pdf(), 'Procuração')->assertSessionHas('success');

        $anexo = SignatureAttachment::sole();
        $this->assertNull($anexo->requirement_key);

        $this->atendente()->get(route('signature-documents.show', $documento))
            ->assertOk()
            ->assertSee('Outros anexos')
            ->assertSee('Procuração');
    }

    public function test_rascunho_remove_e_depois_do_congelamento_nao(): void
    {
        $documento = $this->criaDocumentoDeAssinatura(['template' => $this->modeloComAnexos()]);

        $this->envia($documento, 'mod_documento_de_identidade', $this->pdf());
        $primeiro = SignatureAttachment::sole();

        $this->atendente()->delete(route('signature-documents.attachments.destroy', [$documento, $primeiro]))
            ->assertSessionHas('success');

        $this->assertSame(0, SignatureAttachment::count());
        Storage::disk(config('signature.disk'))->assertMissing($primeiro->path);
        $this->assertSame(1, SignatureAuditEvent::where('event', SignatureAuditEvent::EVENT_ATTACHMENT_REMOVED)->count());

        $this->envia($documento, 'mod_documento_de_identidade', $this->pdf());
        app(SignatureDocumentService::class)->freeze($documento->fresh());

        // Congelado: ainda aceita anexo novo, mas não remove o que entrou.
        $this->envia($documento, 'mod_comprovante_de_residencia', $this->pdf('conta.pdf'))->assertSessionHas('success');

        $anexo = SignatureAttachment::first();
        $this->atendente()->delete(route('signature-documents.attachments.destroy', [$documento, $anexo]))
            ->assertSessionHas('warning');

        $this->assertSame(2, SignatureAttachment::count());
    }

    public function test_documento_cancelado_nao_recebe_anexo(): void
    {
        $documento = app(SignatureDocumentService::class)->freeze($this->criaDocumentoDeAssinatura());
        app(SignatureDocumentService::class)->cancel($documento, 'Desistiu');

        $this->envia($documento, null, $this->pdf(), 'Comprovante')
            ->assertSessionHas('warning', fn($m) => str_contains($m, 'os anexos não mudam mais'));

        $this->assertSame(0, SignatureAttachment::count());
    }

    public function test_arquivo_abre_por_rota_e_preso_ao_documento(): void
    {
        $documento = $this->criaDocumentoDeAssinatura();
        $outro = $this->criaDocumentoDeAssinatura();

        $this->envia($documento, null, $this->pdf(), 'Procuração');
        $anexo = SignatureAttachment::sole();

        $resposta = $this->atendente()->get(route('signature-documents.attachments.show', [$documento, $anexo]))->assertOk();
        $this->assertSame(self::PDF, $resposta->streamedContent());
        $this->assertSame('application/pdf', $resposta->headers->get('Content-Type'));

        $this->atendente()->get(route('signature-documents.attachments.show', [$outro, $anexo]))->assertNotFound();
    }

    public function test_quem_so_consulta_ve_mas_nao_envia(): void
    {
        $documento = $this->criaDocumentoDeAssinatura();

        $this->actingAs($this->usuarioComPermissoes(['assinatura.consultar']))
            ->post(route('signature-documents.attachments.store', $documento), [
                'rotulo' => 'Procuração',
                'arquivo' => $this->pdf(),
            ])
            ->assertForbidden();

        $this->assertSame(0, SignatureAttachment::count());
    }

    /** Assinado e finalizado, com a identidade anexada. */
    private function finalizadoComAnexo(): SignatureDocument
    {
        $documento = $this->criaDocumentoDeAssinatura(['template' => $this->modeloComAnexos()]);
        $this->envia($documento, 'mod_documento_de_identidade', UploadedFile::fake()->image('rg.jpg'));

        $documento = app(SignatureDocumentService::class)->freeze($documento->fresh());
        $estados = app(SignatureStateMachine::class);

        foreach ($documento->signers()->get() as $signatario) {
            $estados->signerTo($signatario, SignatureSigner::STATUS_SIGNED, SignatureAuditEvent::EVENT_SIGNED, ['signed_at' => now()]);
        }

        $estados->documentTo($documento, SignatureDocument::STATUS_SIGNED, SignatureAuditEvent::EVENT_SIGNED);

        $bytes = '%PDF-final';
        $caminho = config('signature.paths.documents') . '/' . $documento->id . '/final.pdf';
        Storage::disk(config('signature.disk'))->put($caminho, $bytes);

        return $estados->documentTo($documento, SignatureDocument::STATUS_FINALIZED, SignatureAuditEvent::EVENT_FINALIZED, [
            'final_path' => $caminho,
            'final_sha256' => hash('sha256', $bytes),
            'finalized_at' => now(),
        ]);
    }

    public function test_manifesto_imprime_o_hash_de_cada_anexo(): void
    {
        $documento = $this->criaDocumentoDeAssinatura(['template' => $this->modeloComAnexos()]);
        $this->envia($documento, 'mod_documento_de_identidade', $this->pdf());
        $documento = app(SignatureDocumentService::class)->freeze($documento->fresh());

        $html = app(SignatureDocumentRenderer::class)->html($documento->fresh(['template', 'signers']), SignatureDocumentRenderer::MODE_FINAL);

        $this->assertStringContainsString('Anexos do documento', $html);
        $this->assertStringContainsString(SignatureAttachment::sole()->sha256, $html);
    }

    public function test_servidor_de_arquivos_recebe_os_anexos_ao_lado_do_pdf(): void
    {
        config(['signature.archive.enabled' => true]);

        $documento = app(SignatureArchiver::class)->archive($this->finalizadoComAnexo());
        $anexo = SignatureAttachment::sole();

        $caminho = preg_replace('/\.pdf$/', '', $documento->archive_path) . ' - anexo 1 - Documento de identidade.jpg';

        $this->assertSame(
            Storage::disk(config('signature.disk'))->get($anexo->path),
            Storage::disk('signature_archive')->get($caminho),
        );

        // Finalizado: não recebe mais nada.
        $this->envia($documento, null, $this->pdf(), 'Outro')->assertSessionHas('warning');
    }
}
