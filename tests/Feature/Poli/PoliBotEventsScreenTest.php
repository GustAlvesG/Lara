<?php

namespace Tests\Feature\Poli;

use App\Authorization\Permissions;
use App\Authorization\UserAccess;
use App\Models\BotAttendanceEvent;
use App\Models\User;
use App\Services\PoliBot\DefaultFlows;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

/**
 * Bot WhatsApp › Transferências e encerramentos pelo caminho de verdade:
 * rota, permissão própria, controller, layout.
 *
 * O usuário é um mock (o model User está preso à conexão mysql): o acesso
 * efetivo tem só as permissões do teste.
 */
class PoliBotEventsScreenTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-16 15:00:00');

        foreach ([
            '2026_09_25_160000_create_poli_bot_tables.php',
            '2026_09_29_120000_add_lara_owner_to_bot_sessions.php',
            '2026_10_16_150000_create_bot_attendance_events_table.php',
        ] as $migration) {
            (require base_path('database/migrations/' . $migration))->up();
        }

        // Sem a Poli: os nomes dos times saem dos fluxos padrão.
        Http::preventStrayRequests();
        Http::fake(fn () => Http::response(['message' => 'fora'], 500));
        config(['poli.base_url' => 'https://foundation-api.poli.digital/v3', 'poli.token' => 't', 'poli.account_uuid' => 'acc', 'poli.http.retry_sleep_ms' => 0]);
        Cache::flush();
    }

    private function usuario(array $permissoes = [Permissions::BOT_WHATSAPP_OCORRENCIAS]): User
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
        $user->name = 'Gustavo Teste';

        return $user;
    }

    private function evento(array $campos): BotAttendanceEvent
    {
        return BotAttendanceEvent::create([
            'occurred_at' => now(),
            'contact_uuid' => 'contato-1',
            'contact_name' => 'LUIZ EDUARDO',
            'contact_phone' => '5524999931600',
            ...$campos,
        ]);
    }

    public function test_mostra_motivo_destino_e_resultado(): void
    {
        $this->evento(['kind' => 'enviada', 'reason' => 'fluxo', 'actor' => 'O Lara', 'target' => DefaultFlows::TEAM_SECRETARIA,
            'outcome' => 'devolvido', 'outcome_detail' => 'O Lara', 'flow_slug' => 'atendimento', 'step_key' => 'secretaria']);
        $this->evento(['kind' => 'recebida', 'reason' => 'retorno_transbordo', 'actor' => 'Poli (sistema)', 'target' => 'O Lara',
            'poli_text' => 'Contato redirecionado para O Lara pelo sistema em 16/10/2026 às 11:50']);
        $this->evento(['kind' => 'encerramento', 'reason' => 'atendente', 'actor' => 'Thais Silva', 'detail' => 'FINISHED_BY_USER']);

        $this->actingAs($this->usuario())
            ->get(route('poli-bot.events.index'))
            ->assertOk()
            ->assertSee('Transferências e encerramentos')
            ->assertSee('Passo de transbordo do fluxo')
            ->assertSee('Secretaria')                                 // o uuid do time vira nome
            ->assertSee('Voltou para O Lara')
            ->assertSee('A Poli devolveu o transbordo para O Lara')
            ->assertSee('pelo sistema em 16/10/2026', false)
            ->assertSee('Encerrado por um atendente')
            ->assertSee('Thais Silva')
            ->assertSee('atendimento › secretaria');
    }

    public function test_filtro_por_motivo_e_resumo_do_periodo(): void
    {
        $this->evento(['kind' => 'recebida', 'reason' => 'entrada', 'contact_name' => 'Contato Entrada']);
        $this->evento(['kind' => 'recebida', 'reason' => 'retorno_transbordo', 'contact_name' => 'Contato Devolvido']);
        $this->evento(['kind' => 'recebida', 'reason' => 'retorno_transbordo', 'contact_name' => 'Contato Devolvido']);

        $html = $this->actingAs($this->usuario())
            ->get(route('poli-bot.events.index', ['tipo' => 'recebida', 'motivo' => 'retorno_transbordo']))
            ->assertOk()
            ->assertSee('Contato Devolvido')
            ->assertDontSee('Contato Entrada')
            ->assertSee('Ver todos os motivos')
            ->getContent();

        // O resumo conta o período inteiro, mesmo com o motivo filtrado.
        $this->assertMatchesRegularExpression('/Entrada pela Poli[^<]*<\/span>\s*<span[^>]*>1<\/span>/', $html);
        $this->assertMatchesRegularExpression('/A Poli devolveu o transbordo[^<]*<\/span>\s*<span[^>]*>2<\/span>/', $html);
    }

    public function test_periodo_padrao_e_o_dia_de_hoje(): void
    {
        $this->evento(['kind' => 'recebida', 'reason' => 'entrada', 'contact_name' => 'De Hoje']);
        $this->evento(['kind' => 'recebida', 'reason' => 'entrada', 'contact_name' => 'De Ontem', 'occurred_at' => now()->subDay()]);

        $this->actingAs($this->usuario())
            ->get(route('poli-bot.events.index'))
            ->assertSee('De Hoje')
            ->assertDontSee('De Ontem');

        $this->actingAs($this->usuario())
            ->get(route('poli-bot.events.index', ['de' => now()->subDay()->format('Y-m-d')]))
            ->assertSee('De Ontem');
    }

    public function test_busca_por_telefone(): void
    {
        $this->evento(['kind' => 'recebida', 'reason' => 'entrada', 'contact_name' => 'Procurado']);
        $this->evento(['kind' => 'recebida', 'reason' => 'entrada', 'contact_name' => 'Outro', 'contact_uuid' => 'c2', 'contact_phone' => '5524988887777']);

        $this->actingAs($this->usuario())
            ->get(route('poli-bot.events.index', ['q' => '(24) 99993-1600']))
            ->assertSee('Procurado')
            ->assertDontSee('Outro');
    }

    public function test_sem_registro_explica_que_o_diario_e_novo(): void
    {
        $this->actingAs($this->usuario())
            ->get(route('poli-bot.events.index'))
            ->assertOk()
            ->assertSee('O registro começou com a versão que criou esta tela');
    }

    /** Permissão própria: editar fluxo não dá acesso, e o menu só mostra o que a pessoa alcança. */
    public function test_exige_a_permissao_propria(): void
    {
        $this->actingAs($this->usuario([Permissions::BOT_WHATSAPP]))
            ->get(route('poli-bot.events.index'))
            ->assertForbidden();

        $this->actingAs($this->usuario([Permissions::BOT_WHATSAPP]))
            ->get(route('poli-bot.index'))
            ->assertOk()
            ->assertDontSee(route('poli-bot.events.index'));
    }

    public function test_menu_mostra_os_dois_itens_para_quem_tem_as_duas_permissoes(): void
    {
        $this->actingAs($this->usuario([Permissions::BOT_WHATSAPP, Permissions::BOT_WHATSAPP_OCORRENCIAS]))
            ->get(route('poli-bot.events.index'))
            ->assertOk()
            ->assertSee(route('poli-bot.index'))
            ->assertSee('Fluxos');
    }
}
