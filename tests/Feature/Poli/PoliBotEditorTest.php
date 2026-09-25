<?php

namespace Tests\Feature\Poli;

use App\Models\BotFlow;
use App\Models\BotFlowVersion;
use App\Models\PoliMessage;
use App\Models\User;
use App\Services\PoliBot\BotEngine;
use App\Services\PoliBot\DefaultFlows;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

/**
 * A tela de fluxos do bot pelo caminho de verdade: rota, middleware de
 * permissão, controller, layout.
 *
 * O usuário é um mock (o model User está preso à conexão mysql — ver
 * MocksPlacarUser): responde `can`/`canAny` só para a permissão do bot, e o
 * resto do que o layout pergunta sai inofensivo.
 */
class PoliBotEditorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        \Illuminate\Support\Carbon::setTestNow('2026-09-23 10:00:00');

        foreach ([
            '2026_09_25_160000_create_poli_bot_tables.php',
            '2026_09_26_100000_create_bot_flow_versions_table.php',
        ] as $migration) {
            (require base_path('database/migrations/' . $migration))->up();
        }

        config([
            'poli.bot.mode' => BotEngine::MODE_SHADOW,
            'poli.base_url' => 'https://foundation-api.poli.digital/v3',
            'poli.token' => 'token-teste',
            'poli.account_uuid' => 'acc-uuid',
            'poli.http.retry_sleep_ms' => 0,
        ]);

        // Nenhuma chamada de verdade à Poli: o que um teste não simular, estoura.
        Http::preventStrayRequests();
        Cache::flush();
    }

    private function usuario(bool $podeEditar = true): User
    {
        $user = Mockery::mock(User::class)->makePartial();

        $permitido = fn ($habilidade) => $podeEditar && in_array('manage whatsapp bot', (array) $habilidade, true);
        $user->shouldReceive('can')->andReturnUsing(fn ($habilidade) => $permitido($habilidade));
        $user->shouldReceive('canAny')->andReturnUsing(fn ($habilidades) => $permitido($habilidades));
        $user->shouldReceive('hasAnyPermission')->andReturn($podeEditar);
        $user->shouldReceive('hasRole')->andReturn(false);
        $user->shouldReceive('isCoordinator')->andReturn(false);
        $user->shouldReceive('belongsToSectorNamed')->andReturn(false);

        $semNotificacoes = Mockery::mock();
        $semNotificacoes->shouldReceive('latest')->andReturnSelf();
        $semNotificacoes->shouldReceive('limit')->andReturnSelf();
        $semNotificacoes->shouldReceive('get')->andReturn(collect());
        $user->shouldReceive('unreadNotifications')->andReturn($semNotificacoes);

        $user->id = 7;
        $user->name = 'Gustavo Teste';

        return $user;
    }

    private function instalarPadrao(): void
    {
        foreach (DefaultFlows::all() as $slug => $fluxo) {
            BotFlow::create(['slug' => $slug, 'name' => $fluxo['name'], 'active' => true, 'definition' => $fluxo['definition']]);
        }
    }

    private function fluxoValido(array $mudancas = []): array
    {
        return array_replace_recursive([
            'name' => 'Pesquisa de satisfação',
            'slug' => 'pesquisa',
            'active' => false,
            'definition' => [
                'start' => 'nota',
                'triggers' => ['any' => false, 'texts' => ['pesquisa'], 'only_goto' => false],
                'timeout_minutes' => 10,
                'max_attempts' => 3,
                'steps' => [
                    'nota' => [
                        'say' => ['type' => 'text', 'text' => 'De 1 a 5, que nota você dá?', 'params' => []],
                        'expect' => ['type' => 'number', 'min' => 1, 'max' => 5, 'pattern' => ''],
                        'save_as' => 'nota',
                        'invalid' => '',
                        'next' => 'obrigado',
                    ],
                    'obrigado' => [
                        'say' => ['type' => 'text', 'text' => 'Obrigado pela nota {nota}!'],
                    ],
                ],
            ],
        ], $mudancas);
    }

    /* ---------------- acesso e telas ---------------- */

    public function test_sem_permissao_nao_entra(): void
    {
        $this->actingAs($this->usuario(false))
            ->get(route('poli-bot.index'))
            ->assertForbidden();
    }

    public function test_lista_mostra_os_fluxos_e_o_modo(): void
    {
        $this->instalarPadrao();
        PoliMessage::create(['uuid' => 'm1', 'contact_uuid' => 'c1', 'direction' => 'IN', 'type' => 'TEXT', 'texto' => 'oi', 'shadow' => true]);

        $this->actingAs($this->usuario())
            ->get(route('poli-bot.index'))
            ->assertOk()
            ->assertSee('Atendimento inicial')
            ->assertSee('Carro de aplicativo')
            ->assertSee('Modo sombra')
            ->assertSee('Qualquer primeira mensagem')
            ->assertSee('Bot WhatsApp');   // item do menu
    }

    public function test_editor_abre_com_o_fluxo(): void
    {
        $this->instalarPadrao();
        $fluxo = BotFlow::where('slug', 'atendimento')->first();

        $this->actingAs($this->usuario())
            ->get(route('poli-bot.flows.edit', $fluxo))
            ->assertOk()
            ->assertSee('botFlowEditor', false)
            ->assertSee(DefaultFlows::TPL_DEPARTAMENTOS, false)
            ->assertSee('Simulador');

        $this->actingAs($this->usuario())->get(route('poli-bot.flows.create'))->assertOk()->assertSee('Novo fluxo');
    }

    /* ---------------- gravar ---------------- */

    public function test_cria_fluxo_limpo_e_com_versao(): void
    {
        $this->actingAs($this->usuario())
            ->postJson(route('poli-bot.flows.store'), $this->fluxoValido())
            ->assertOk()
            ->assertJsonPath('ok', true);

        $fluxo = BotFlow::where('slug', 'pesquisa')->sole();
        $this->assertFalse($fluxo->active);
        $this->assertArrayNotHasKey('invalid', $fluxo->definition['steps']['nota'], 'texto vazio não é gravado');
        $this->assertArrayNotHasKey('pattern', $fluxo->definition['steps']['nota']['expect']);
        $this->assertSame(5, $fluxo->definition['steps']['nota']['expect']['max']);

        $versao = BotFlowVersion::sole();
        $this->assertSame('Gustavo Teste', $versao->user_name);
        $this->assertSame(7, (int) $versao->user_id);
    }

    public function test_fluxo_com_problemas_nao_grava_e_diz_o_que_corrigir(): void
    {
        $dados = $this->fluxoValido(['definition' => ['steps' => ['nota' => ['next' => 'fantasma']]]]);

        $resposta = $this->actingAs($this->usuario())
            ->postJson(route('poli-bot.flows.store'), $dados)
            ->assertStatus(422);

        $this->assertStringContainsString('"fantasma" não existe', implode(' ', $resposta->json('errors.definition')));
        $this->assertSame(0, BotFlow::count());
    }

    public function test_identificador_invalido_ou_repetido_e_recusado(): void
    {
        $this->actingAs($this->usuario())
            ->postJson(route('poli-bot.flows.store'), $this->fluxoValido(['slug' => 'Com Espaço']))
            ->assertStatus(422)->assertJsonValidationErrors('slug');

        $this->actingAs($this->usuario())->postJson(route('poli-bot.flows.store'), $this->fluxoValido())->assertOk();
        $this->actingAs($this->usuario())
            ->postJson(route('poli-bot.flows.store'), $this->fluxoValido())
            ->assertStatus(422)->assertJsonValidationErrors('slug');
    }

    public function test_so_um_fluxo_ativo_abre_em_qualquer_mensagem(): void
    {
        $this->instalarPadrao();

        $resposta = $this->actingAs($this->usuario())
            ->postJson(route('poli-bot.flows.store'), $this->fluxoValido([
                'active' => true,
                'definition' => ['triggers' => ['any' => true, 'texts' => []]],
            ]))
            ->assertStatus(422);

        $this->assertStringContainsString('já abre em qualquer primeira mensagem', implode(' ', $resposta->json('errors.definition')));
    }

    public function test_editar_mantem_o_slug_e_empilha_versoes(): void
    {
        $this->actingAs($this->usuario())->postJson(route('poli-bot.flows.store'), $this->fluxoValido())->assertOk();
        $fluxo = BotFlow::sole();

        $dados = $this->fluxoValido(['name' => 'Pesquisa v2', 'slug' => 'outro-slug']);
        $this->actingAs($this->usuario())
            ->putJson(route('poli-bot.flows.update', $fluxo), $dados)
            ->assertOk()
            ->assertJsonPath('version.name', 'Pesquisa v2');

        $this->assertSame('pesquisa', $fluxo->fresh()->slug);
        $this->assertSame('Pesquisa v2', $fluxo->fresh()->name);
        $this->assertSame(2, BotFlowVersion::count());
    }

    public function test_ativar_fluxo_quebrado_e_recusado(): void
    {
        $fluxo = BotFlow::create(['slug' => 'quebrado', 'name' => 'Quebrado', 'active' => false, 'definition' => ['start' => 'x', 'steps' => []]]);

        $this->actingAs($this->usuario())
            ->postJson(route('poli-bot.flows.toggle', $fluxo))
            ->assertStatus(422);

        $this->assertFalse($fluxo->fresh()->active);
    }

    public function test_ativar_e_desativar(): void
    {
        $this->actingAs($this->usuario())->postJson(route('poli-bot.flows.store'), $this->fluxoValido())->assertOk();
        $fluxo = BotFlow::sole();

        $this->actingAs($this->usuario())->postJson(route('poli-bot.flows.toggle', $fluxo))->assertOk()->assertJsonPath('active', true);
        $this->actingAs($this->usuario())->post(route('poli-bot.flows.toggle', $fluxo))->assertRedirect();

        $this->assertFalse($fluxo->fresh()->active);
    }

    public function test_nao_apaga_fluxo_chamado_por_outro(): void
    {
        $this->instalarPadrao();
        $carro = BotFlow::where('slug', 'carro-de-aplicativo')->first();

        $this->actingAs($this->usuario())
            ->deleteJson(route('poli-bot.flows.destroy', $carro))
            ->assertStatus(422)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'Atendimento inicial'));

        $this->assertNotNull($carro->fresh());
    }

    public function test_apaga_fluxo_solto(): void
    {
        $this->actingAs($this->usuario())->postJson(route('poli-bot.flows.store'), $this->fluxoValido())->assertOk();

        $this->actingAs($this->usuario())
            ->deleteJson(route('poli-bot.flows.destroy', BotFlow::sole()))
            ->assertOk();

        $this->assertSame(0, BotFlow::count());
        $this->assertSame(0, BotFlowVersion::count());
    }

    /* ---------------- simulador ---------------- */

    public function test_simulador_conversa_e_nunca_envia(): void
    {
        Http::fake();
        config(['poli.bot.mode' => BotEngine::MODE_ON]);
        $this->instalarPadrao();

        $this->actingAs($this->usuario())
            ->postJson(route('poli-bot.simulate'), ['acao' => 'enviar', 'texto' => 'oi'])
            ->assertOk()
            ->assertJsonPath('replies.0.type', 'TEMPLATE')
            ->assertJsonPath('replies.0.options.1.label', 'Financeiro')
            ->assertJsonPath('session.step', 'menu');

        $this->actingAs($this->usuario())
            ->postJson(route('poli-bot.simulate'), ['acao' => 'enviar', 'texto' => "Carro de Aplicativo\nCarro, moto ou táxi"])
            ->assertOk()
            ->assertJsonPath('session.flow', 'carro-de-aplicativo');

        Http::assertNothingSent();
        $this->assertTrue(PoliMessage::where('contact_uuid', 'painel-7')->where('direction', 'OUT')->get()->every->shadow);
    }

    public function test_simulador_testa_rascunho_e_aceita_imagem(): void
    {
        $this->actingAs($this->usuario())->postJson(route('poli-bot.flows.store'), $this->fluxoValido())->assertOk();

        $this->actingAs($this->usuario())
            ->postJson(route('poli-bot.simulate'), ['acao' => 'comecar', 'fluxo' => 'pesquisa'])
            ->assertOk()
            ->assertJsonPath('replies.0.text', 'De 1 a 5, que nota você dá?');

        $this->actingAs($this->usuario())
            ->postJson(route('poli-bot.simulate'), ['acao' => 'enviar', 'texto' => '9'])
            ->assertOk()
            ->assertJsonPath('session.tentativas', 1);

        $this->actingAs($this->usuario())
            ->postJson(route('poli-bot.simulate'), ['acao' => 'enviar', 'texto' => '4'])
            ->assertOk()
            ->assertJsonPath('replies.0.text', 'Obrigado pela nota 4!');

        $this->actingAs($this->usuario())
            ->postJson(route('poli-bot.simulate'), ['acao' => 'enviar', 'imagem' => true])
            ->assertOk();

        $this->actingAs($this->usuario())->postJson(route('poli-bot.simulate'), ['acao' => 'reiniciar'])->assertOk();
        $this->assertSame(0, PoliMessage::where('contact_uuid', 'painel-7')->count());
    }

    /* ---------------- dados da Poli ---------------- */

    public function test_templates_da_poli_vem_com_as_opcoes_e_ficam_em_cache(): void
    {
        Http::fake(['*/templates*' => Http::response(['data' => [
            ['uuid' => 'tpl-1', 'key' => 'Lista Departamentos', 'type' => 'LIST', 'status' => 'NOT_VERIFIED',
             'message' => ['body' => 'Selecione:', 'section' => [['rows' => [
                 ['messageOption' => ['title' => 'Financeiro', 'description' => 'Débitos']],
             ]]]]],
            ['uuid' => 'tpl-2', 'key' => 'Colônia', 'type' => 'WABA', 'message' => ['body' => 'Saber mais?', 'buttons' => [['text' => 'Sim']]]],
        ]], 200)]);

        $this->actingAs($this->usuario())
            ->getJson(route('poli-bot.poli.templates'))
            ->assertOk()
            ->assertJsonPath('data.0.options.0', ['label' => 'Financeiro', 'description' => 'Débitos'])
            ->assertJsonPath('data.1.options.0.label', 'Sim');

        $this->actingAs($this->usuario())->getJson(route('poli-bot.poli.templates'))->assertOk();
        Http::assertSentCount(1);
    }

    public function test_poli_fora_do_ar_nao_quebra_o_editor(): void
    {
        Http::fake(['*' => Http::response(['message' => 'erro'], 500)]);

        $this->actingAs($this->usuario())
            ->getJson(route('poli-bot.poli.teams'))
            ->assertStatus(502)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'colar o uuid'));
    }
}
