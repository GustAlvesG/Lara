<?php

namespace Tests\Feature;

use App\Models\SignatureDocument;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesSignatureSchema;
use Tests\Concerns\MocksSignatureUser;
use Tests\TestCase;

/**
 * Quem alcança o quê no painel.
 *
 * São quatro permissões separadas de propósito — escrever o texto de um termo
 * não é a mesma coisa que atender no balcão, e nenhuma das duas é ver a foto
 * de uma pessoa. Este teste cobre as fronteiras entre elas.
 *
 * Sem `RefreshDatabase` e com User mockado — ver CreatesSignatureSchema e
 * MocksSignatureUser.
 */
class SignaturePanelAccessTest extends TestCase
{
    use CreatesSignatureSchema;
    use MocksSignatureUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSignatureSchema();
        $this->createPermissionSchema();

        Storage::fake(config('signature.disk'));
    }

    public function test_sem_permissao_nenhuma_o_painel_nao_abre(): void
    {
        $this->actingAs($this->usuarioComPermissoes([]))
            ->get(route('signature-documents.index'))
            ->assertForbidden();

        $this->actingAs($this->usuarioComPermissoes([]))
            ->get(route('signature-templates.index'))
            ->assertForbidden();
    }

    public function test_quem_atende_ve_documentos_mas_nao_escreve_modelos(): void
    {
        $usuario = $this->usuarioComPermissoes(['manage signature documents']);

        $this->actingAs($usuario)
            ->get(route('signature-documents.index'))
            ->assertOk();

        // Escrever o texto de um termo é ato jurídico, e não operação de balcão.
        $this->actingAs($usuario)
            ->get(route('signature-templates.index'))
            ->assertForbidden();
    }

    public function test_quem_so_consulta_nao_cria_documento(): void
    {
        $usuario = $this->usuarioComPermissoes(['view signed documents']);

        $this->actingAs($usuario)
            ->get(route('signature-documents.index'))
            ->assertOk();

        $this->actingAs($usuario)
            ->get(route('signature-documents.create'))
            ->assertForbidden();
    }

    public function test_criar_documento_pelo_painel_grava_signatario(): void
    {
        $modelo = $this->criaModeloDeAssinatura();

        $resposta = $this->actingAs($this->usuarioComPermissoes(['manage signature documents']))
            ->post(route('signature-documents.store'), [
                'signature_template_id' => $modelo->id,
                'title' => 'Termo de responsabilidade — Piscina',
                'signers' => [
                    ['name' => 'Maria de Souza', 'cpf' => '123.456.789-09', 'role' => 'signer'],
                ],
            ]);

        $documento = SignatureDocument::latest('id')->first();

        $resposta->assertRedirect(route('signature-documents.show', $documento));

        // O CPF é gravado só com dígitos, venha com máscara ou sem.
        $this->assertDatabaseHas('signature_signers', [
            'signature_document_id' => $documento->id,
            'cpf' => '12345678909',
            'position' => 1,
        ]);
    }

    public function test_congelar_exige_permissao_de_operacao(): void
    {
        $documento = $this->criaDocumentoDeAssinatura();

        $this->actingAs($this->usuarioComPermissoes(['view signed documents']))
            ->post(route('signature-documents.freeze', $documento))
            ->assertForbidden();

        $this->assertNull($documento->fresh()->frozen_at);
    }

    public function test_congelar_pelo_painel_gera_pdf_e_libera_para_assinatura(): void
    {
        $documento = $this->criaDocumentoDeAssinatura();

        $this->actingAs($this->usuarioComPermissoes(['manage signature documents']))
            ->post(route('signature-documents.freeze', $documento))
            ->assertRedirect(route('signature-documents.show', $documento));

        $documento->refresh();

        $this->assertSame(SignatureDocument::STATUS_AWAITING_SIGNATURE, $documento->status);
        Storage::disk(config('signature.disk'))->assertExists($documento->original_path);
    }

    /**
     * O PDF sai por ROTA, com autorização — nunca por URL de disco. Este
     * projeto não tem sequer o link `public/storage`.
     */
    public function test_pdf_so_sai_para_quem_pode_ver_o_documento(): void
    {
        $documento = $this->criaDocumentoDeAssinatura();

        $this->actingAs($this->usuarioComPermissoes(['manage signature documents']))
            ->post(route('signature-documents.freeze', $documento));

        $this->actingAs($this->usuarioComPermissoes([]))
            ->get(route('signature-documents.pdf', $documento))
            ->assertForbidden();

        $this->actingAs($this->usuarioComPermissoes(['view signed documents']))
            ->get(route('signature-documents.pdf', $documento))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_documento_sem_pdf_responde_404_em_vez_de_vazar_caminho(): void
    {
        $documento = $this->criaDocumentoDeAssinatura();

        $this->actingAs($this->usuarioComPermissoes(['view signed documents']))
            ->get(route('signature-documents.pdf', $documento))
            ->assertNotFound();
    }

    public function test_editar_documento_congelado_e_recusado_pela_policy(): void
    {
        $documento = $this->criaDocumentoDeAssinatura();

        $this->actingAs($this->usuarioComPermissoes(['manage signature documents']))
            ->post(route('signature-documents.freeze', $documento));

        $this->actingAs($this->usuarioComPermissoes(['manage signature documents']))
            ->get(route('signature-documents.edit', $documento))
            ->assertForbidden();
    }
}
