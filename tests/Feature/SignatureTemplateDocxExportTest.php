<?php

namespace Tests\Feature;

use App\Services\Signature\DocxTemplateExporter;
use App\Services\Signature\DocxTemplateImporter;
use Tests\Concerns\CreatesSignatureSchema;
use Tests\Concerns\MocksSignatureUser;
use Tests\TestCase;

/**
 * Base em Word do modelo (DocxTemplateExporter): baixar, editar no Word e
 * reenviar em "Revisar". O que importa é a VOLTA — o arquivo reimportado
 * precisa dar as mesmas chaves de campo e de parte, senão o formulário perde
 * tipo, pergunta e opções (ele casa os campos pela chave).
 */
class SignatureTemplateDocxExportTest extends TestCase
{
    use CreatesSignatureSchema;
    use MocksSignatureUser;

    private ?string $arquivo = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSignatureSchema();
        $this->createPermissionSchema();
    }

    protected function tearDown(): void
    {
        if ($this->arquivo !== null) {
            @unlink($this->arquivo);
        }

        parent::tearDown();
    }

    /** Exporta o modelo e importa o arquivo de volta. */
    private function idaEVolta($modelo): array
    {
        $this->arquivo = tempnam(sys_get_temp_dir(), 'tpldocx');
        file_put_contents($this->arquivo, app(DocxTemplateExporter::class)->export($modelo));

        return app(DocxTemplateImporter::class)->import($this->arquivo);
    }

    public function test_o_word_reimportado_volta_com_as_mesmas_chaves_e_a_mesma_estrutura(): void
    {
        $modelo = $this->criaModeloDeAssinatura([
            'name' => 'Locação do salão',
            'body_html' => '<h2>1. Objeto</h2>'
                . '<p>O <strong>Contratante</strong> loca o salão em [[data_evento]], pelo valor de [[valor_total]].</p>'
                . '<ul><li>Limpeza inclusa</li><li>Som <em>até 22h</em></li></ul>'
                . '<table><tr><th>Item</th><th>Qtd.</th></tr><tr><td>Mesas</td><td>[[mesas]]</td></tr></table>'
                . '[[assinatura:contratante]]' . "\n" . '[[assinatura:contratado]]',
            'variables' => [
                // Rótulo que daria OUTRA chave: o marcador sai pela chave.
                ['key' => 'data_evento', 'label' => 'Data do evento', 'type' => 'date', 'required' => true],
                // Rótulo que dá a mesma chave: o marcador sai pelo rótulo.
                ['key' => 'valor_total', 'label' => 'Valor total', 'type' => 'money', 'required' => true],
                ['key' => 'mesas', 'label' => 'Mesas', 'type' => 'text', 'required' => false],
            ],
            'parties' => [
                ['key' => 'contratante', 'label' => 'Contratante'],
                ['key' => 'contratado', 'label' => 'Empresa contratada'],
            ],
        ]);

        $volta = $this->idaEVolta($modelo);

        $this->assertSame(['data_evento', 'valor_total', 'mesas'], array_column($volta['variables'], 'key'));
        $this->assertSame(['contratante', 'contratado'], array_column($volta['parties'], 'key'));
        $this->assertTrue($volta['has_signature']);

        $html = $volta['html'];
        $this->assertStringContainsString('<h2>1. Objeto</h2>', $html);
        $this->assertStringContainsString('<strong>Contratante</strong>', $html);
        $this->assertStringContainsString('[[data_evento]]', $html);
        $this->assertStringContainsString('[[valor_total]]', $html);
        $this->assertStringContainsString('<ul>', $html);
        $this->assertStringContainsString('<li>Som <em>até 22h</em></li>', $html);
        $this->assertStringContainsString('<table>', $html);
        $this->assertStringContainsString('<td>[[mesas]]</td>', $html);
        // Cada marcador de assinatura sozinho na linha, fora de <p>.
        $this->assertStringContainsString("[[assinatura:contratante]]\n[[assinatura:contratado]]", $html);
        $this->assertStringNotContainsString('<p>[[assinatura', $html);
    }

    public function test_o_marcador_generico_de_assinatura_volta_igual(): void
    {
        $volta = $this->idaEVolta($this->criaModeloDeAssinatura());

        $this->assertSame([], $volta['parties']);
        $this->assertTrue($volta['has_signature']);
        $this->assertStringContainsString('<p>Declaro estar ciente das regras de uso.</p>', $volta['html']);
        $this->assertStringContainsString('[[assinatura]]', $volta['html']);
    }

    public function test_baixar_o_word_do_modelo(): void
    {
        $modelo = $this->criaModeloDeAssinatura(['name' => 'Termo da piscina']);

        $resposta = $this->actingAs($this->usuarioComPermissoes(['assinatura.modelos']))
            ->get(route('signature-templates.docx', $modelo))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

        $this->assertStringContainsString('modelo-termo-da-piscina-v1.docx', $resposta->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('PK', $resposta->getContent());

        $this->actingAs($this->usuarioComPermissoes(['assinatura.modelos']))
            ->get(route('signature-templates.show', $modelo))
            ->assertSee(route('signature-templates.docx', $modelo));
    }

    public function test_sem_permissao_de_modelos_nao_baixa(): void
    {
        $modelo = $this->criaModeloDeAssinatura();

        $this->actingAs($this->usuarioComPermissoes(['assinatura.documentos']))
            ->get(route('signature-templates.docx', $modelo))
            ->assertForbidden();
    }
}
