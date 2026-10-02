<?php

namespace Tests\Feature;

use App\Models\SignatureTemplate;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\BuildsDocx;
use Tests\Concerns\CreatesSignatureSchema;
use Tests\Concerns\MocksSignatureUser;
use Tests\TestCase;

/**
 * Escrever um modelo a partir do Word, pela tela.
 *
 * A conversão em si está em DocxTemplateImporterTest. Aqui fica o que é da
 * tela: quem pode enviar, o que volta para o formulário e a rede que impede
 * um campo do texto de ficar sem declaração.
 *
 * Sem `RefreshDatabase` e com User mockado — ver CreatesSignatureSchema e
 * MocksSignatureUser.
 */
class SignatureTemplateDocxImportTest extends TestCase
{
    use BuildsDocx;
    use CreatesSignatureSchema;
    use MocksSignatureUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSignatureSchema();
        $this->createPermissionSchema();
    }

    protected function tearDown(): void
    {
        $this->apagaDocxTemporarios();

        parent::tearDown();
    }

    private function envio(string $caminho, string $nome = 'termo.docx'): UploadedFile
    {
        return new UploadedFile($caminho, $nome, null, null, true);
    }

    public function test_o_formulario_oferece_o_envio_do_word(): void
    {
        $this->actingAs($this->usuarioComPermissoes(['assinatura.modelos']))
            ->get(route('signature-templates.create'))
            ->assertOk()
            ->assertSee('Escolher arquivo .docx')
            ->assertSee(route('signature-templates.import-docx'));
    }

    public function test_enviar_o_docx_devolve_o_texto_e_os_campos_sem_gravar_nada(): void
    {
        $docx = $this->criaDocx(
            $this->docxP('Uso do espaço [[Espaço utilizado]].') . $this->docxP('[[assinatura]]'),
        );

        $this->actingAs($this->usuarioComPermissoes(['assinatura.modelos']))
            ->postJson(route('signature-templates.import-docx'), ['arquivo' => $this->envio($docx)])
            ->assertOk()
            ->assertJson([
                'html' => "<p>Uso do espaço [[espaco_utilizado]].</p>\n[[assinatura]]",
                'variables' => [
                    ['key' => 'espaco_utilizado', 'label' => 'Espaço utilizado', 'required' => true],
                ],
                'has_signature' => true,
                'warnings' => [],
            ]);

        // A importação só preenche o formulário; quem grava é o salvar.
        $this->assertSame(0, SignatureTemplate::count());
    }

    public function test_arquivo_que_nao_e_docx_volta_com_a_orientacao(): void
    {
        $pdf = tempnam(sys_get_temp_dir(), 'pdf');
        file_put_contents($pdf, '%PDF-1.4');

        try {
            $this->actingAs($this->usuarioComPermissoes(['assinatura.modelos']))
                ->postJson(route('signature-templates.import-docx'), ['arquivo' => $this->envio($pdf, 'termo.pdf')])
                ->assertStatus(422)
                ->assertJsonFragment(['message' => 'O arquivo não é um documento do Word (.docx). Se ele é um '
                    . '.doc antigo ou um PDF, abra no Word e use Arquivo → Salvar como → Documento do Word (.docx).']);
        } finally {
            @unlink($pdf);
        }
    }

    public function test_quem_so_atende_nao_importa_modelo(): void
    {
        $docx = $this->criaDocx($this->docxP('Texto.'));

        $this->actingAs($this->usuarioComPermissoes(['assinatura.documentos']))
            ->postJson(route('signature-templates.import-docx'), ['arquivo' => $this->envio($docx)])
            ->assertForbidden();
    }

    /**
     * Um `[[campo]]` no texto sem declaração não vira pergunta para o
     * atendente e sairia impresso, com colchetes, no documento que a pessoa
     * assina. Salvar declara por conta própria.
     */
    public function test_salvar_declara_o_campo_que_esta_no_texto_e_ninguem_declarou(): void
    {
        $this->actingAs($this->usuarioComPermissoes(['assinatura.modelos']))
            ->post(route('signature-templates.store'), [
                'name' => 'Termo de uso',
                'body_html' => '<p>Espaço [[espaco]] em [[data_evento]].</p>[[assinatura]]',
                'variables' => [
                    ['key' => 'espaco', 'label' => 'Espaço utilizado', 'required' => '0'],
                ],
                'identity_check' => SignatureTemplate::IDENTITY_PARTIAL,
            ])
            ->assertRedirect();

        $campos = collect(SignatureTemplate::firstOrFail()->declaredVariables())
            ->map(fn(array $campo) => [$campo['key'], $campo['label'], $campo['required'], $campo['type']])
            ->all();

        $this->assertSame([
            // O que a pessoa declarou fica como ela declarou.
            ['espaco', 'Espaço utilizado', false, 'text'],
            // O que faltou entra obrigatório, como texto e do atendente — ninguém
            // olhou para ele ainda. A assinatura não é campo.
            ['data_evento', 'Data evento', true, 'text'],
        ], $campos);
    }
}
