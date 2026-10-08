<?php

namespace Tests\Feature;

use App\Authorization\Permissions as P;
use App\Models\SignatureKioskDevice;
use App\Models\SignatureMinorTerm;
use App\Models\SignatureTemplate;
use Database\Seeders\MinorTermTemplateSeeder;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesSignatureSchema;
use Tests\Concerns\MocksSignatureUser;
use Tests\TestCase;

/**
 * Assinaturas → Termo de Menores, no computador: termos (modelo + vigência),
 * pareamento do tablet e histórico. Cada tela com a sua permissão.
 */
class SignatureMinorTermPanelTest extends TestCase
{
    use CreatesSignatureSchema;
    use MocksSignatureUser;

    private SignatureTemplate $modelo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSignatureSchema();
        $this->createPermissionSchema();

        Storage::fake(config('signature.disk'));

        (new MinorTermTemplateSeeder())->run();
        $this->modelo = SignatureTemplate::where('name', MinorTermTemplateSeeder::NAME)->firstOrFail();
    }

    private function termo(string $inicio, string $fim, array $extra = []): SignatureMinorTerm
    {
        return SignatureMinorTerm::create(array_merge([
            'name' => 'Evento',
            'signature_template_id' => $this->modelo->id,
            'starts_on' => $inicio,
            'ends_on' => $fim,
        ], $extra));
    }

    public function test_permissoes_estao_no_catalogo(): void
    {
        $nomes = P::all();

        $this->assertContains(P::ASSINATURA_TERMO_MENORES_GERENCIAR, $nomes);
        $this->assertContains(P::ASSINATURA_TERMO_MENORES_PAREAR, $nomes);
        $this->assertContains(P::ASSINATURA_TERMO_MENORES_HISTORICO, $nomes);
    }

    public function test_cada_tela_pede_a_sua_permissao(): void
    {
        $historico = $this->usuarioComPermissoes([P::ASSINATURA_TERMO_MENORES_HISTORICO]);

        $this->actingAs($historico)->get(route('minor-terms.index'))->assertRedirect(route('minor-terms.history'));
        $this->actingAs($historico)->get(route('minor-terms.history'))->assertOk();
        $this->actingAs($historico)->get(route('minor-terms.terms'))->assertForbidden();
        $this->actingAs($historico)->get(route('minor-terms.devices'))->assertForbidden();

        $nada = $this->usuarioComPermissoes([P::ASSINATURA_DOCUMENTOS]);
        $this->actingAs($nada)->get(route('minor-terms.index'))->assertForbidden();
    }

    public function test_cadastra_termo_com_o_modelo_do_seeder(): void
    {
        $usuario = $this->usuarioComPermissoes([P::ASSINATURA_TERMO_MENORES_GERENCIAR]);

        $this->actingAs($usuario)->get(route('minor-terms.terms'))->assertOk()->assertSee(MinorTermTemplateSeeder::NAME);

        $this->actingAs($usuario)->post(route('minor-terms.store'), [
            'name' => 'OKTOBERPET 2026',
            'signature_template_id' => $this->modelo->id,
            'starts_on' => '2026-07-10',
            'ends_on' => '2026-07-12',
        ])->assertRedirect(route('minor-terms.terms'))->assertSessionHasNoErrors();

        $termo = SignatureMinorTerm::firstOrFail();
        $this->assertSame('Atendente de Teste', $termo->created_by_name);
        $this->assertTrue($termo->active);
    }

    public function test_vigencias_nao_se_cruzam(): void
    {
        $this->termo('2026-07-10', '2026-07-12', ['name' => 'OKTOBERPET']);
        $usuario = $this->usuarioComPermissoes([P::ASSINATURA_TERMO_MENORES_GERENCIAR]);

        $this->actingAs($usuario)->post(route('minor-terms.store'), [
            'name' => 'Outro',
            'signature_template_id' => $this->modelo->id,
            'starts_on' => '2026-07-12',
            'ends_on' => '2026-07-15',
        ])->assertSessionHasErrors('starts_on');

        // Encostado, sem cruzar: aceito.
        $this->actingAs($usuario)->post(route('minor-terms.store'), [
            'name' => 'Outro',
            'signature_template_id' => $this->modelo->id,
            'starts_on' => '2026-07-13',
            'ends_on' => '2026-07-15',
        ])->assertSessionHasNoErrors();
    }

    public function test_modelo_que_nao_serve_ao_autoatendimento_e_recusado(): void
    {
        $semCampos = $this->criaModeloDeAssinatura([
            'variables' => [['key' => 'espaco', 'label' => 'Espaço', 'required' => true, 'type' => 'text']],
        ]);

        $usuario = $this->usuarioComPermissoes([P::ASSINATURA_TERMO_MENORES_GERENCIAR]);

        $this->actingAs($usuario)->post(route('minor-terms.store'), [
            'name' => 'Evento',
            'signature_template_id' => $semCampos->id,
            'starts_on' => '2026-07-10',
            'ends_on' => '2026-07-12',
        ])->assertSessionHasErrors('signature_template_id');

        $this->assertSame(0, SignatureMinorTerm::count());
    }

    public function test_modelo_com_cpf_tipado_e_recusado(): void
    {
        $variaveis = $this->modelo->variables;
        $variaveis[2]['type'] = 'cpf';
        $tipado = $this->criaModeloDeAssinatura(['variables' => $variaveis, 'requires_photo' => true]);

        $problemas = \App\Services\Signature\MinorTerms\MinorTermFields::templateProblems($tipado);

        $this->assertNotEmpty($problemas);
        $this->assertStringContainsString('precisa ser do tipo Texto', implode(' ', $problemas));
    }

    public function test_desativar_tira_o_termo_do_tablet(): void
    {
        $termo = $this->termo(now()->toDateString(), now()->toDateString());
        $usuario = $this->usuarioComPermissoes([P::ASSINATURA_TERMO_MENORES_GERENCIAR]);

        $this->assertNotNull(SignatureMinorTerm::current());

        $this->actingAs($usuario)->put(route('minor-terms.update', $termo), [
            'name' => 'Evento',
            'signature_template_id' => $this->modelo->id,
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->toDateString(),
            'active' => '0',
        ])->assertSessionHasNoErrors();

        $this->assertNull(SignatureMinorTerm::current());
    }

    public function test_gera_qr_de_pareamento_e_acompanha_o_status(): void
    {
        $usuario = $this->usuarioComPermissoes([P::ASSINATURA_TERMO_MENORES_PAREAR]);

        $this->actingAs($usuario)->get(route('minor-terms.devices'))->assertOk()->assertSee(route('quiosque.menores.index'));

        $resposta = $this->actingAs($usuario)
            ->postJson(route('minor-terms.devices.pair'), ['name' => 'Tablet da entrada'])
            ->assertOk();

        $this->assertStringStartsWith('data:image/svg+xml;base64,', $resposta->json('qr_src'));

        $device = SignatureKioskDevice::findOrFail($resposta->json('device'));
        $this->assertSame('Atendente de Teste', $device->paired_by_name);
        // Só o hash fica no banco.
        $this->assertSame(64, strlen($device->pairing_token_hash));

        $this->actingAs($usuario)->getJson($resposta->json('status_url'))
            ->assertOk()->assertJsonPath('paired', false)->assertJsonPath('awaiting', true);
    }

    public function test_historico_lista_vazio_sem_erro(): void
    {
        $this->termo(now()->toDateString(), now()->toDateString(), ['name' => 'OKTOBERPET 2026']);

        $usuario = $this->usuarioComPermissoes([P::ASSINATURA_TERMO_MENORES_HISTORICO]);

        $this->actingAs($usuario)->get(route('minor-terms.history'))
            ->assertOk()
            ->assertSee('OKTOBERPET 2026')
            ->assertSee('Nenhuma autorização com esses filtros.');
    }

    public function test_guia_tem_a_secao_e_abre_para_quem_so_ve_o_historico(): void
    {
        $usuario = $this->usuarioComPermissoes([P::ASSINATURA_TERMO_MENORES_HISTORICO]);

        $this->actingAs($usuario)->get(route('signature-guide.content'))
            ->assertOk()
            ->assertSee('Termo de Menores (autoatendimento nos eventos)')
            ->assertSee('<code>/assinatura/kiosk/menores</code>', false)
            ->assertDontSee('%%ENDERECO_DO_TABLET_MENORES%%', false);
    }

    public function test_menu_mostra_o_item_para_quem_tem_alguma_das_permissoes(): void
    {
        $usuario = $this->usuarioComPermissoes([P::ASSINATURA_TERMO_MENORES_PAREAR]);

        $this->actingAs($usuario)->get(route('minor-terms.devices'))
            ->assertOk()
            ->assertSee(route('minor-terms.index'));
    }
}
