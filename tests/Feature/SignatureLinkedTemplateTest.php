<?php

namespace Tests\Feature;

use App\Models\SignatureDocument;
use App\Models\SignatureTemplate;
use App\Services\Signature\LinkedTemplateComposer;
use App\Services\Signature\MinorTerms\MinorTermFields;
use App\Services\Signature\SignatureDocumentRenderer;
use App\Services\Signature\SignatureDocumentService;
use App\Services\Signature\SignatureFieldTypes as T;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesSignatureSchema;
use Tests\Concerns\MocksSignatureUser;
use Tests\TestCase;

/**
 * Campo restrito ao atendente e modelo vinculado (29/10/2026).
 *
 * Vínculo: "quem assina X assina também Y", num sentido só. O texto de Y
 * entra no MESMO documento de X; campos de Y ligados a campos de X (mesmo
 * tipo, chaves iguais ou não) são preenchidos uma vez; partes de Y ligadas a
 * uma de X assinam ali, e as não ligadas se somam como partes novas. Ver
 * LinkedTemplateComposer.
 */
class SignatureLinkedTemplateTest extends TestCase
{
    use CreatesSignatureSchema;
    use MocksSignatureUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSignatureSchema();
        $this->createPermissionSchema();

        Storage::fake(config('signature.disk'));
        Queue::fake();
    }

    private function modeloY(array $attributes = []): SignatureTemplate
    {
        return $this->criaModeloDeAssinatura(array_merge([
            'name' => 'Termo de imagem',
            'body_html' => '<p>Eu, [[nome_completo]], autorizo o uso da imagem. Contato: [[telefone]].</p>[[assinatura]]',
            'identity_check' => SignatureTemplate::IDENTITY_FULL,
            'requires_photo' => true,
            'attachments' => [['key' => 'mod_identidade', 'label' => 'Identidade', 'required' => true]],
            'variables' => [
                ['key' => 'nome_completo', 'label' => 'Nome completo', 'type' => T::TEXT, 'required' => true],
                ['key' => 'telefone', 'label' => 'Telefone', 'type' => T::PHONE],
            ],
        ], $attributes));
    }

    private function modeloX(SignatureTemplate $y, array $vinculo = [], array $attributes = []): SignatureTemplate
    {
        return $this->criaModeloDeAssinatura(array_merge([
            'name' => 'Contrato de uso',
            'body_html' => '<p>Contratante: [[nome]]. Telefone: [[telefone]].</p>[[assinatura]]',
            'identity_check' => SignatureTemplate::IDENTITY_PARTIAL,
            'requires_photo' => false,
            'variables' => [
                ['key' => 'nome', 'label' => 'Nome', 'type' => T::TEXT, 'required' => true],
                ['key' => 'telefone', 'label' => 'Telefone', 'type' => T::PHONE],
            ],
            'linked_templates' => [array_merge(['root_id' => $y->root_id, 'fields' => ['nome_completo' => 'nome']], $vinculo)],
        ], $attributes));
    }

    /* ------------------------------------------------------------------
     | Campo restrito ao atendente
     |------------------------------------------------------------------*/

    public function test_modelo_grava_campo_restrito_ao_atendente(): void
    {
        $this->actingAs($this->usuarioComPermissoes(['assinatura.modelos']))
            ->post(route('signature-templates.store'), [
                'name' => 'Ficha',
                'body_html' => '<p>[[valor]] [[hoje]]</p>',
                'identity_check' => SignatureTemplate::IDENTITY_PARTIAL,
                'variables' => [
                    ['key' => 'valor', 'label' => 'Valor', 'type' => T::MONEY, 'attendant_only' => '1'],
                    // Automático não é de ninguém: a marca não se aplica.
                    ['key' => 'hoje', 'label' => 'Hoje', 'type' => T::DATE_SIGNING, 'attendant_only' => '1'],
                ],
            ])
            ->assertSessionHasNoErrors();

        [$valor, $hoje] = SignatureTemplate::firstOrFail()->declaredVariables();

        $this->assertTrue($valor['attendant_only']);
        $this->assertFalse($hoje['attendant_only']);
    }

    public function test_campo_restrito_nao_vai_ao_tablet_nem_forcado(): void
    {
        $modelo = $this->criaModeloDeAssinatura([
            'body_html' => '<p>[[valor]] [[telefone]]</p>[[assinatura]]',
            'variables' => [
                ['key' => 'valor', 'label' => 'Valor', 'type' => T::MONEY, 'attendant_only' => true],
                ['key' => 'telefone', 'label' => 'Telefone', 'type' => T::PHONE],
            ],
        ]);
        $usuario = $this->usuarioComPermissoes(['assinatura.documentos']);

        // A tela não oferece a caixa para o campo restrito.
        $this->actingAs($usuario)->get(route('signature-documents.create', ['template' => $modelo->id]))
            ->assertOk()
            ->assertDontSee('name="ask_signer[]" value="valor"', false)
            ->assertSee('name="ask_signer[]" value="telefone"', false)
            ->assertSee('Só o atendente responde (regra do modelo)');

        // E o servidor recusa a marca forçada.
        $this->actingAs($usuario)->post(route('signature-documents.store'), [
            'signature_template_id' => $modelo->id,
            'ask_signer' => ['valor'],
            'signers' => [['name' => 'Maria de Souza', 'cpf' => '123.456.789-09']],
        ])->assertSessionHasErrors('data.valor');

        // Mesmo gravado por fora, o documento não pergunta o campo restrito.
        $documento = $this->criaDocumentoDeAssinatura(['template' => $modelo, 'signer_field_keys' => ['valor', 'telefone']]);

        $this->assertSame(['telefone'], array_column($documento->signerFields(), 'key'));
    }

    /* ------------------------------------------------------------------
     | Composição
     |------------------------------------------------------------------*/

    public function test_composto_junta_textos_campos_e_soma_as_regras(): void
    {
        $y = $this->modeloY();
        $composto = $this->modeloX($y)->composed();

        // nome_completo (Y) ligado a nome (X); telefone tem a mesma chave e o mesmo tipo.
        $this->assertSame(['nome', 'telefone'], array_column($composto->declaredVariables(), 'key'));

        $this->assertStringContainsString('page-break-before: always', $composto->body_html);
        $this->assertStringContainsString('Eu, [[nome]], autorizo', $composto->body_html);
        $this->assertStringNotContainsString('[[nome_completo]]', $composto->body_html);

        // A mais exigente: foto de Y, CPF completo de Y, anexo de Y.
        $this->assertTrue($composto->requires_photo);
        $this->assertSame(SignatureTemplate::IDENTITY_FULL, $composto->identity_check);
        $this->assertSame(['mod_identidade'], array_column($composto->declaredAttachments(), 'key'));
        $this->assertSame([['id' => $y->id, 'root_id' => $y->root_id, 'name' => 'Termo de imagem', 'version' => 1]], $composto->composedParts);

        // Obrigatório em Y torna obrigatório o campo ligado.
        $this->assertTrue(collect($composto->declaredVariables())->firstWhere('key', 'nome')['required']);
    }

    public function test_campo_proprio_com_chave_repetida_ganha_sufixo(): void
    {
        $composto = $this->modeloX($this->modeloY(), ['fields' => ['nome_completo' => 'nome', 'telefone' => null]])->composed();

        $this->assertSame(['nome', 'telefone', 'telefone_2'], array_column($composto->declaredVariables(), 'key'));
        $this->assertStringContainsString('Contato: [[telefone_2]]', $composto->body_html);
    }

    public function test_composto_nao_e_gravado(): void
    {
        $composto = $this->modeloX($this->modeloY())->composed();

        $this->assertFalse($composto->save());
        $this->assertSame(2, SignatureTemplate::count());
    }

    public function test_partes_ligadas_ficam_e_as_outras_se_somam(): void
    {
        $y = $this->criaModeloDeAssinatura([
            'name' => 'Anexo',
            'body_html' => '<p>Anexo.</p>[[assinatura:locatario]][[assinatura:testemunha]]',
            'parties' => [['key' => 'locatario', 'label' => 'Locatário'], ['key' => 'testemunha', 'label' => 'Testemunha']],
        ]);
        $x = $this->criaModeloDeAssinatura([
            'name' => 'Contrato',
            'body_html' => '<p>Contrato.</p>[[assinatura:contratante]]',
            'parties' => [['key' => 'contratante', 'label' => 'Contratante']],
            'linked_templates' => [['root_id' => $y->root_id, 'parties' => ['locatario' => 'contratante']]],
        ]);

        $composto = $x->composed();

        $this->assertSame(['contratante', 'testemunha'], array_column($composto->declaredParties(), 'key'));
        $this->assertSame(2, substr_count($composto->body_html, '[[assinatura:contratante]]'));
        $this->assertStringContainsString('[[assinatura:testemunha]]', $composto->body_html);

        // A parte nova pede signatário no documento.
        $documento = app(SignatureDocumentService::class)->create($x, [], [
            ['name' => 'Maria de Souza', 'cpf' => '12345678909', 'party' => 'contratante'],
        ], 1, 'Atendente');

        $this->expectExceptionMessage('Falta informar quem assina como: Testemunha.');

        app(SignatureDocumentService::class)->freeze($documento);
    }

    public function test_assinatura_generica_de_y_vai_para_todos_de_x(): void
    {
        $y = $this->modeloY();
        $x = $this->criaModeloDeAssinatura([
            'body_html' => '<p>Contrato.</p>[[assinatura:contratante]][[assinatura:contratado]]',
            'parties' => [['key' => 'contratante', 'label' => 'Contratante'], ['key' => 'contratado', 'label' => 'Contratado']],
            'linked_templates' => [['root_id' => $y->root_id]],
        ]);

        $parteY = explode('page-break-before', $x->composed()->body_html)[1];

        $this->assertStringContainsString('[[assinatura:contratante]][[assinatura:contratado]][[assinatura]]', $parteY);
    }

    public function test_documento_leva_as_versoes_do_dia_e_assina_nas_duas_partes(): void
    {
        $y = $this->modeloY(['requires_photo' => false, 'identity_check' => SignatureTemplate::IDENTITY_PARTIAL, 'attachments' => []]);
        $x = $this->modeloX($y);
        $usuario = $this->usuarioComPermissoes(['assinatura.documentos']);

        // O formulário já traz os campos dos dois e avisa o que entra junto.
        $this->actingAs($usuario)->get(route('signature-documents.create', ['template' => $x->id]))
            ->assertOk()
            ->assertSee('Inclui também, no mesmo documento:')
            ->assertSee('Termo de imagem (v1)')
            ->assertSee('name="data[nome]"', false)
            ->assertDontSee('name="data[nome_completo]"', false);

        $this->actingAs($usuario)->post(route('signature-documents.store'), [
            'signature_template_id' => $x->id,
            'data' => ['nome' => 'Maria de Souza', 'telefone' => '(24) 99999-1234'],
            'signers' => [['name' => 'Maria de Souza', 'cpf' => '123.456.789-09']],
        ])->assertSessionHasNoErrors();

        $documento = SignatureDocument::firstOrFail();

        $this->assertEquals([$y->root_id => $y->id], $documento->linked_templates);
        $this->assertSame(['Termo de imagem (versão 1)'], $documento->linkedTemplateLabels());

        // Y revisado depois não muda o documento já criado.
        $y->newVersion(['body_html' => '<p>Texto novo de Y.</p>[[assinatura]]'], 1);

        $documento = app(SignatureDocumentService::class)->freeze($documento->fresh());

        $this->assertStringContainsString('Contratante: Maria de Souza.', $documento->body_snapshot);
        $this->assertStringContainsString('Eu, Maria de Souza, autorizo', $documento->body_snapshot);
        $this->assertStringNotContainsString('Texto novo de Y', $documento->body_snapshot);

        $html = app(SignatureDocumentRenderer::class)->html($documento->fresh());

        // O mesmo signatário assina no lugar de X e no de Y.
        $this->assertSame(2, substr_count($html, 'class="sig-area"'));
    }

    public function test_modelo_exigido_desativado_barra_documento_novo(): void
    {
        $y = $this->modeloY();
        $x = $this->modeloX($y);
        SignatureTemplate::where('root_id', $y->root_id)->update(['active' => false]);

        $this->actingAs($this->usuarioComPermissoes(['assinatura.documentos']))
            ->post(route('signature-documents.store'), [
                'signature_template_id' => $x->id,
                'signers' => [['name' => 'Maria de Souza', 'cpf' => '123.456.789-09']],
            ])
            ->assertSessionHasErrors('signature_template_id');
    }

    /* ------------------------------------------------------------------
     | Cadastro do vínculo
     |------------------------------------------------------------------*/

    /** @return array<string, mixed> */
    private function envioDoModelo(array $vinculos, array $extra = []): array
    {
        return array_merge([
            'name' => 'Contrato de uso',
            'body_html' => '<p>[[nome]] [[valor]]</p>[[assinatura]]',
            'identity_check' => SignatureTemplate::IDENTITY_PARTIAL,
            'variables' => [
                ['key' => 'nome', 'label' => 'Nome', 'type' => T::TEXT],
                ['key' => 'valor', 'label' => 'Valor', 'type' => T::MONEY],
            ],
            'linked_templates' => $vinculos,
        ], $extra);
    }

    public function test_vinculo_grava_a_correspondencia_conferida(): void
    {
        $y = $this->modeloY();

        $this->actingAs($this->usuarioComPermissoes(['assinatura.modelos']))
            ->post(route('signature-templates.store'), $this->envioDoModelo([[
                'root_id' => $y->root_id,
                // Campo que Y não tem é descartado; '' = campo próprio.
                'fields' => ['nome_completo' => 'nome', 'telefone' => '', 'inexistente' => 'nome'],
                'parties' => [LinkedTemplateComposer::GENERIC => LinkedTemplateComposer::GENERIC],
            ]]))
            ->assertSessionHasNoErrors();

        $x = SignatureTemplate::where('name', 'Contrato de uso')->firstOrFail();

        $this->assertSame([[
            'root_id' => $y->root_id,
            'fields' => ['nome_completo' => 'nome', 'telefone' => null],
            'parties' => [LinkedTemplateComposer::GENERIC => LinkedTemplateComposer::GENERIC],
        ]], $x->declaredLinks());
    }

    public function test_vinculo_recusa_tipo_diferente_e_a_si_mesmo(): void
    {
        $y = $this->modeloY();
        $usuario = $this->usuarioComPermissoes(['assinatura.modelos']);

        // Telefone (Y) não pode ser o mesmo campo que Valor (X).
        $this->actingAs($usuario)
            ->post(route('signature-templates.store'), $this->envioDoModelo([[
                'root_id' => $y->root_id, 'fields' => ['telefone' => 'valor'],
            ]]))
            ->assertSessionHasErrors('linked_templates.0.fields');

        $this->actingAs($usuario)
            ->put(route('signature-templates.update', $y), $this->envioDoModelo([['root_id' => $y->root_id]]))
            ->assertSessionHasErrors('linked_templates.0.root_id');
    }

    public function test_vinculo_e_de_um_sentido_so(): void
    {
        $y = $this->modeloY();
        $x = $this->modeloX($y);

        // Y exigir X fecharia o ciclo.
        $this->actingAs($this->usuarioComPermissoes(['assinatura.modelos']))
            ->put(route('signature-templates.update', $y), $this->envioDoModelo([['root_id' => $x->root_id]]))
            ->assertSessionHasErrors('linked_templates.0.root_id');

        // E um documento só de Y continua só Y.
        $this->assertSame($y->id, $y->composed()->id);
        $this->assertSame([], $y->composed()->composedParts);
    }

    public function test_y_exigido_por_outro_documento_conta_como_em_uso(): void
    {
        $y = $this->modeloY();
        $x = $this->modeloX($y);

        app(SignatureDocumentService::class)->create($x, [], [['name' => 'Maria', 'cpf' => '12345678909']], 1, 'Atendente');

        $this->assertTrue($y->inUse());

        $this->actingAs($this->usuarioComPermissoes(['assinatura.modelos']))
            ->delete(route('signature-templates.destroy', $y))
            ->assertRedirect()
            ->assertSessionHas('success', fn(string $m) => str_contains($m, 'desativado') && str_contains($m, 'Contrato de uso'));

        $this->assertSame(1, SignatureTemplate::where('root_id', $y->root_id)->count());
    }

    public function test_telas_do_vinculo_renderizam(): void
    {
        $y = $this->modeloY();
        $x = $this->modeloX($y);
        $usuario = $this->usuarioComPermissoes(['assinatura.modelos']);

        $this->actingAs($usuario)->getJson(route('signature-templates.link-info', $y))
            ->assertOk()
            ->assertJsonPath('name', 'Termo de imagem')
            ->assertJsonPath('variables.0.key', 'nome_completo');

        $this->actingAs($usuario)->get(route('signature-templates.edit', $x))
            ->assertOk()
            ->assertSee('Modelos exigidos junto')
            ->assertSee('Só o atendente responde');

        $this->actingAs($usuario)->get(route('signature-templates.show', $x))
            ->assertOk()
            ->assertSee('Termo de imagem (v1)');
    }

    public function test_termo_de_menores_recusa_modelo_com_vinculo(): void
    {
        $x = $this->modeloX($this->modeloY());

        $this->assertContains(
            'O modelo exige outros modelos junto; o Termo de Menores leva só o próprio termo. Tire o vínculo '
                . 'ou use outro modelo.',
            MinorTermFields::templateProblems($x),
        );
    }
}
