<?php

namespace Tests\Feature\Replay;

use App\Authorization\Permissions as P;
use App\Authorization\UserAccess;
use App\Models\User;
use App\Services\Replay\RepetClient;
use App\Services\Replay\RepetUnavailable;
use App\View\Navigation;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

/**
 * RePET › Transmissões: o Lara repassa ao servidor do rePET (Http::fake —
 * nenhum teste fala com o servidor de verdade, que publica no YouTube).
 *
 * Sem banco: usuário mock, como nas outras telas (User preso ao mysql).
 */
class ReplayBroadcastTest extends TestCase
{
    private const BASE = 'http://repet.test:8000';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config(['services.repet.url' => self::BASE . '/', 'services.repet.token' => 'repet_teste']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function usuario(array $permissoes = [P::REPLAY_TRANSMISSAO]): User
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('access')->andReturn(new UserAccess($permissoes));
        $user->shouldReceive('isCoordinator')->andReturn(false);
        $user->shouldReceive('belongsToSectorNamed')->andReturn(false);

        $semNotificacoes = Mockery::mock();
        $semNotificacoes->shouldReceive('latest')->andReturnSelf();
        $semNotificacoes->shouldReceive('limit')->andReturnSelf();
        $semNotificacoes->shouldReceive('get')->andReturn(collect());
        $user->shouldReceive('unreadNotifications')->andReturn($semNotificacoes);

        $user->id = 7;
        $user->name = 'Operador Teste';

        return $user;
    }

    private function estado(array $placar = ['a' => ['pontos' => 1, 'sets' => 0], 'b' => ['pontos' => 0, 'sets' => 0]]): array
    {
        return [
            'quadra_id' => 'loc1-quadra1', 'no_ar' => true, 'transmitindo' => true,
            'nomes' => ['a' => 'ANA E BIA', 'b' => 'CRIS E DUDA'],
            'placar' => $placar, 'volume' => 100,
            'no_ar_ha_segundos' => 60, 'encerra_em_segundos' => 7140,
            'reinicios' => 0, 'travamentos' => 0, 'audio_da_camera' => true,
        ];
    }

    // ── Cliente ──────────────────────────────────────────────────────────

    public function test_status_vai_com_o_token_bearer_e_a_url_sem_barra_dupla(): void
    {
        Http::fake([self::BASE . '/status' => Http::response(['quadras' => [], 'transmissoes_com_placar' => ['no_ar' => 0, 'limite' => 2]])]);

        $estado = app(RepetClient::class)->status();

        $this->assertSame(2, $estado['transmissoes_com_placar']['limite']);
        Http::assertSent(fn (HttpRequest $r) => $r->url() === self::BASE . '/status'
            && $r->hasHeader('Authorization', 'Bearer repet_teste'));
    }

    public function test_ligar_sem_nomes_nao_manda_campos_vazios(): void
    {
        Http::fake([self::BASE . '/broadcast/loc1-quadra1/start' => Http::response(['status' => 'iniciada'] + $this->estado())]);

        app(RepetClient::class)->startBroadcast('loc1-quadra1', ['nome_a' => null, 'nome_b' => '', 'reset' => true]);

        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'POST' && $r->data() === ['reset' => true]);
    }

    public function test_detail_do_servidor_vira_a_mensagem(): void
    {
        $detail = "A stream key de 'loc1-quadra1' é a mesma de 'loc1-quadra2', que já está no ar; encerre 'loc1-quadra2' antes.";
        Http::fake(['*' => Http::response(['detail' => $detail], 409)]);

        try {
            app(RepetClient::class)->startBroadcast('loc1-quadra1');
            $this->fail('Deveria lançar RepetUnavailable.');
        } catch (RepetUnavailable $e) {
            $this->assertSame($detail, $e->getMessage());
            $this->assertSame(409, $e->status);
        }
    }

    public function test_detail_em_lista_da_validacao_automatica(): void
    {
        Http::fake(['*' => Http::response(['detail' => [['type' => 'missing', 'loc' => ['body', 'nivel'], 'msg' => 'Field required']]], 422)]);

        $this->expectExceptionMessage('Field required');
        app(RepetClient::class)->volume('loc1-quadra1', 100);
    }

    public function test_token_recusado_vira_aviso_para_a_ti(): void
    {
        Http::fake(['*' => Http::response(['detail' => 'Token inválido.'], 401)]);

        $this->expectExceptionMessage('recusou a chave do Lara');
        app(RepetClient::class)->status();
    }

    public function test_timeout_num_ponto_marca_incerto_e_nao_repete(): void
    {
        $tentativas = 0;
        Http::fake(function () use (&$tentativas) {
            $tentativas++;
            throw new ConnectionException('cURL error 28: Operation timed out');
        });

        try {
            app(RepetClient::class)->point('loc1-quadra1', 'a');
            $this->fail('Deveria lançar RepetUnavailable.');
        } catch (RepetUnavailable $e) {
            $this->assertTrue($e->uncertain);
            $this->assertStringContainsString('Confira', $e->getMessage());
        }

        $this->assertSame(1, $tentativas);
    }

    public function test_quadra_id_com_caminho_e_recusado_antes_de_chamar(): void
    {
        Http::fake();

        $this->expectException(\InvalidArgumentException::class);
        app(RepetClient::class)->point('../painel', 'a');
    }

    // ── Rotas ────────────────────────────────────────────────────────────

    public function test_ponto_repassa_lado_e_delta_e_devolve_o_estado(): void
    {
        Http::fake([self::BASE . '/broadcast/loc1-quadra1/ponto/b*' => Http::response($this->estado(['a' => ['pontos' => 1, 'sets' => 0], 'b' => ['pontos' => 0, 'sets' => 0]]))]);

        $this->actingAs($this->usuario())
            ->postJson(route('replay.broadcasts.point', 'loc1-quadra1'), ['lado' => 'b', 'delta' => -1])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('state.nomes.a', 'ANA E BIA');

        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'POST'
            && $r->url() === self::BASE . '/broadcast/loc1-quadra1/ponto/b?delta=-1');
    }

    public function test_lado_e_delta_invalidos_nem_chegam_ao_servidor(): void
    {
        Http::fake();

        $this->actingAs($this->usuario())
            ->postJson(route('replay.broadcasts.point', 'loc1-quadra1'), ['lado' => 'c', 'delta' => 5])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lado', 'delta']);

        Http::assertNothingSent();
    }

    public function test_ligar_direta_chama_a_live_da_camera(): void
    {
        Http::fake([self::BASE . '/live/loc1-quadra1' => Http::response(['quadra_id' => 'loc1-quadra1', 'status' => 'iniciada', 'max_seconds' => 7200.0])]);

        $this->actingAs($this->usuario())
            ->postJson(route('replay.broadcasts.start', 'loc1-quadra1'), ['modo' => 'direta'])
            ->assertOk()
            ->assertJsonPath('state.status', 'iniciada');

        Http::assertSent(fn (HttpRequest $r) => $r->url() === self::BASE . '/live/loc1-quadra1');
    }

    public function test_recusa_do_servidor_volta_com_a_mensagem(): void
    {
        Http::fake(['*' => Http::response(['detail' => 'limite de 2 transmissões simultâneas atingido.'], 409)]);

        $this->actingAs($this->usuario())
            ->postJson(route('replay.broadcasts.start', 'loc1-quadra1'), ['modo' => 'placar'])
            ->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('message', 'limite de 2 transmissões simultâneas atingido.');
    }

    public function test_correcao_manda_so_os_campos_preenchidos(): void
    {
        Http::fake(['*' => Http::response($this->estado())]);

        $this->actingAs($this->usuario())
            ->putJson(route('replay.broadcasts.score', 'loc1-quadra1'), ['pontos_a' => 40, 'sets_b' => null])
            ->assertOk();

        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'PUT' && $r->data() === ['pontos_a' => 40]);
    }

    public function test_sem_url_ou_token_a_integracao_fica_desligada(): void
    {
        config(['services.repet.token' => null]);
        Http::fake();

        $this->actingAs($this->usuario())
            ->getJson(route('replay.broadcasts.status'))
            ->assertStatus(503)
            ->assertJsonPath('ok', false);

        Http::assertNothingSent();
    }

    public function test_sem_a_permissao_de_transmissao_e_403_mesmo_com_a_do_replay(): void
    {
        Http::fake();

        $this->actingAs($this->usuario([P::REPLAY]))
            ->postJson(route('replay.broadcasts.point', 'loc1-quadra1'), ['lado' => 'a'])
            ->assertForbidden();

        Http::assertNothingSent();
    }

    // ── Canal do YouTube ─────────────────────────────────────────────────

    private function canal(array $extra = []): array
    {
        return $extra + [
            'quadra_id' => 'loc1-quadra1', 'tem_stream_key' => true, 'stream_key_final' => 'qrst',
            'rtmp_url' => 'rtmp://a.rtmp.youtube.com/live2', 'rtmp_url_padrao' => true,
            'rtmp_hosts_permitidos' => ['youtube.com'], 'chave_compartilhada_com' => [],
            'chave_em_uso_por' => null, 'no_ar' => false,
        ];
    }

    public function test_canal_pede_a_permissao_propria(): void
    {
        Http::fake();

        $this->actingAs($this->usuario())
            ->getJson(route('replay.broadcasts.channel', 'loc1-quadra1'))
            ->assertForbidden();

        $this->actingAs($this->usuario([P::REPLAY_TRANSMISSAO, P::REPLAY_TRANSMISSAO_CANAL]))
            ->putJson(route('replay.broadcasts.channel.update', 'loc1-quadra1'), ['stream_key' => 'abcd-efgh-ijkl'])
            ->assertStatus(200);

        Http::assertSentCount(1);
    }

    public function test_trocar_a_key_manda_so_o_que_veio_e_ela_nao_volta_ao_navegador(): void
    {
        Http::fake([self::BASE . '/quadras/loc1-quadra1/youtube' => Http::response(
            // Mesmo que o servidor devolva a key inteira, ela não passa.
            $this->canal(['status' => 'atualizada', 'stream_key' => 'abcd-efgh-ijkl-mnop-qrst']),
        )]);

        $resposta = $this->actingAs($this->usuario([P::REPLAY_TRANSMISSAO, P::REPLAY_TRANSMISSAO_CANAL]))
            ->putJson(route('replay.broadcasts.channel.update', 'loc1-quadra1'), ['stream_key' => 'abcd-efgh-ijkl-mnop-qrst', 'rtmp_url' => ''])
            ->assertOk()
            ->assertJsonPath('state.stream_key_final', 'qrst');

        $this->assertStringNotContainsString('abcd-efgh-ijkl-mnop-qrst', $resposta->getContent());
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'PUT' && $r->data() === ['stream_key' => 'abcd-efgh-ijkl-mnop-qrst']);
    }

    public function test_canal_vazio_ou_url_que_nao_e_rtmp_nem_chega_ao_servidor(): void
    {
        Http::fake();
        $quem = $this->usuario([P::REPLAY_TRANSMISSAO, P::REPLAY_TRANSMISSAO_CANAL]);

        $this->actingAs($quem)->putJson(route('replay.broadcasts.channel.update', 'loc1-quadra1'), [])->assertStatus(422);
        $this->actingAs($quem)->putJson(route('replay.broadcasts.channel.update', 'loc1-quadra1'), ['rtmp_url' => 'https://youtube.com'])
            ->assertJsonValidationErrors('rtmp_url');
        $this->actingAs($quem)->putJson(route('replay.broadcasts.channel.update', 'loc1-quadra1'), ['stream_key' => 'curta'])
            ->assertJsonValidationErrors('stream_key');

        Http::assertNothingSent();
    }

    public function test_quadra_no_ar_devolve_a_recusa_do_servidor(): void
    {
        Http::fake(['*' => Http::response(['detail' => "'loc1-quadra1' está no ar; encerre a transmissão antes de trocar o destino."], 409)]);

        $this->actingAs($this->usuario([P::REPLAY_TRANSMISSAO, P::REPLAY_TRANSMISSAO_CANAL]))
            ->putJson(route('replay.broadcasts.channel.update', 'loc1-quadra1'), ['rtmp_url' => 'rtmp://a.rtmp.youtube.com/live2'])
            ->assertStatus(422)
            ->assertJsonPath('ok', false);
    }

    // ── Tela e menu ──────────────────────────────────────────────────────

    public function test_a_tela_abre_e_a_aba_so_aparece_para_quem_tem_a_permissao(): void
    {
        $this->actingAs($this->usuario())
            ->get(route('replay.broadcasts.index'))
            ->assertOk()
            ->assertSee('aria-label="Páginas de RePET"', false)
            ->assertSee('repetBroadcasts(', false);

        $request = Request::create('/dashboard');
        $so = collect(Navigation::build($this->usuario(), $request)['links'])->firstWhere('label', 'RePET');
        $semTransmissao = collect(Navigation::build($this->usuario([P::REPLAY]), $request)['links'])->firstWhere('label', 'RePET');

        $this->assertSame('replay.broadcasts.index', $so['route']);
        $this->assertCount(1, $so['children']);
        $this->assertNotContains('replay.broadcasts.index', array_column($semTransmissao['children'], 'route'));
    }

    public function test_a_permissao_esta_no_catalogo_do_repet(): void
    {
        $this->assertTrue(P::exists(P::REPLAY_TRANSMISSAO));
        $this->assertSame('RePET', P::group(P::REPLAY_TRANSMISSAO));
        $this->assertTrue(P::exists(P::REPLAY_TRANSMISSAO_CANAL));
        $this->assertSame('RePET', P::group(P::REPLAY_TRANSMISSAO_CANAL));
    }
}
