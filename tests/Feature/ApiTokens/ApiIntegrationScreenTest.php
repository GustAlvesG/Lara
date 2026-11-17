<?php

namespace Tests\Feature\ApiTokens;

use App\Authorization\Permissions as P;
use App\Authorization\UserAccess;
use App\Models\ApiTokens\ApiIntegration;
use App\Models\ApiTokens\ApiIntegrationToken;
use App\Models\User;
use App\Services\ApiTokens\ApiTokenManager;
use App\Support\ApiTokens\ApiScopes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

/**
 * Administração › Integrações: quem vê, quem gera, o token aparecendo uma
 * vez só, e o último uso de cada token na tela. Usuário mockado (o User está
 * preso à conexão mysql — ver MocksPlacarUser).
 */
class ApiIntegrationScreenTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (require database_path('migrations/2026_11_17_100000_create_api_integrations_tables.php'))->up();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function usuario(array $permissions): User
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('access')->andReturn($permissions ? new UserAccess($permissions) : UserAccess::none());
        $user->shouldReceive('isCoordinator')->andReturn(false);

        $semNotificacoes = Mockery::mock();
        $semNotificacoes->shouldReceive('latest')->andReturnSelf();
        $semNotificacoes->shouldReceive('limit')->andReturnSelf();
        $semNotificacoes->shouldReceive('get')->andReturn(collect());
        $user->shouldReceive('unreadNotifications')->andReturn($semNotificacoes);

        $user->id = 7;
        $user->name = 'Pessoa da TI';

        return $user;
    }

    private function integration(): ApiIntegration
    {
        return ApiIntegration::create(['name' => 'Site de locação', 'system' => 'nextjs', 'active' => true]);
    }

    public function test_sem_permissao_nao_entra(): void
    {
        $integration = $this->integration();

        $this->actingAs($this->usuario([]))->get(route('api-integrations.index'))->assertForbidden();
        $this->actingAs($this->usuario([]))->get(route('api-integrations.show', $integration))->assertForbidden();
    }

    public function test_quem_so_visualiza_ve_o_ultimo_uso_mas_nao_gera(): void
    {
        Carbon::setTestNow('2026-11-17 10:00:00');
        $integration = $this->integration();
        $t = app(ApiTokenManager::class)->generate($integration, 'produção', [ApiScopes::SITE], now()->addDays(90));
        DB::table('api_integration_tokens')->where('id', $t['token']->id)->update([
            'last_used_at' => '2026-11-17 09:58:00', 'last_used_ip' => '10.1.2.3', 'last_used_route' => 'api.tickets.events.index',
        ]);

        $user = $this->usuario([P::INTEGRACOES_VISUALIZAR]);

        $this->actingAs($user)->get(route('api-integrations.index'))
            ->assertOk()->assertSee('Site de locação')->assertSee('1 token ativo')->assertDontSee('Cadastrar integração');

        $this->actingAs($user)->get(route('api-integrations.show', $integration))
            ->assertOk()
            ->assertSee('produção')
            ->assertSee('17/11/2026 09:58')
            ->assertSee('10.1.2.3')
            ->assertSee('api.tickets.events.index')
            ->assertSee('lara_' . $t['token']->token_hint)
            ->assertDontSee($t['plain'])
            ->assertDontSee('Gerar token');

        $this->actingAs($user)->post(route('api-integrations.tokens.store', $integration), [
            'name' => 'homologação', 'scopes' => [ApiScopes::SITE], 'validade' => '90',
        ])->assertForbidden();
    }

    public function test_gerar_mostra_o_token_uma_vez_e_guarda_so_o_hash(): void
    {
        $integration = $this->integration();
        $user = $this->usuario([P::INTEGRACOES_GERENCIAR]);

        $response = $this->actingAs($user)->post(route('api-integrations.tokens.store', $integration), [
            'name' => 'produção', 'scopes' => [ApiScopes::SITE], 'validade' => '365',
        ]);
        $response->assertRedirect(route('api-integrations.show', $integration));

        $plain = session('new_api_token')['plain'];
        $this->assertStringStartsWith('lara_', $plain);

        $token = ApiIntegrationToken::sole();
        $this->assertSame(hash('sha256', $plain), $token->token_hash);
        $this->assertSame(7, $token->created_by);
        $this->assertSame([ApiScopes::SITE], $token->scopes);
        $this->assertTrue($token->expires_at->isSameDay(now()->addDays(365)));

        // Na tela seguinte o texto aparece; na outra, não mais.
        $this->actingAs($user)->get(route('api-integrations.show', $integration))->assertSee($plain);
        $this->actingAs($user)->get(route('api-integrations.show', $integration))->assertDontSee($plain)->assertSee('nunca usado');
    }

    public function test_validade_e_obrigatoria_e_aceita_data_ou_sem_validade(): void
    {
        $integration = $this->integration();
        $user = $this->usuario([P::INTEGRACOES_GERENCIAR]);
        $url = route('api-integrations.tokens.store', $integration);

        $this->actingAs($user)->post($url, ['name' => 'a', 'scopes' => [ApiScopes::SITE]])->assertSessionHasErrors('validade');
        $this->actingAs($user)->post($url, ['name' => 'a', 'scopes' => [ApiScopes::SITE], 'validade' => 'data'])->assertSessionHasErrors('validade_data');
        $this->actingAs($user)->post($url, ['name' => 'a', 'scopes' => [], 'validade' => 'sem'])->assertSessionHasErrors('scopes');
        $this->assertSame(0, ApiIntegrationToken::count());

        $this->actingAs($user)->post($url, ['name' => 'a', 'scopes' => [ApiScopes::SITE], 'validade' => 'data', 'validade_data' => now()->addMonth()->toDateString()])->assertSessionHasNoErrors();
        $this->actingAs($user)->post($url, ['name' => 'b', 'scopes' => [ApiScopes::SITE], 'validade' => 'sem'])->assertSessionHasNoErrors();

        $this->assertNotNull(ApiIntegrationToken::where('name', 'a')->value('expires_at'));
        $this->assertNull(ApiIntegrationToken::where('name', 'b')->value('expires_at'));
    }

    public function test_cadastrar_rotacionar_e_revogar_pela_tela(): void
    {
        $user = $this->usuario([P::INTEGRACOES_GERENCIAR]);

        $this->actingAs($user)->post(route('api-integrations.store'), ['name' => 'HAL', 'system' => 'ia'])->assertRedirect();
        $integration = ApiIntegration::where('name', 'HAL')->sole();

        $t = app(ApiTokenManager::class)->generate($integration, 'VM', [ApiScopes::IA], null);

        $this->actingAs($user)->post(route('api-integrations.tokens.rotate', $t['token']), ['grace_days' => 7, 'validade' => '90'])
            ->assertRedirect(route('api-integrations.show', $integration));
        $this->assertNotNull(session('new_api_token'));
        $this->assertSame(2, $integration->tokens()->count());
        $this->assertNotNull($t['token']->fresh()->expires_at);

        $this->actingAs($user)->post(route('api-integrations.tokens.revoke', $t['token']), ['reason' => 'vazou'])->assertRedirect();
        $this->assertSame('vazou', $t['token']->fresh()->revoke_reason);
        $this->assertSame(7, $t['token']->fresh()->revoked_by);

        $this->actingAs($user)->get(route('api-integrations.show', $integration))->assertOk()->assertSee('revogado');
    }

    public function test_lista_quem_ainda_usa_o_token_antigo(): void
    {
        DB::table('api_token_usage_daily')->insert([
            'day' => now()->toDateString(), 'api_integration_token_id' => 0, 'kind' => 'legado', 'scope' => 'ia',
            'ip' => '192.168.50.4', 'hits' => 12, 'last_route' => 'api.guias.search', 'last_seen_at' => now(),
        ]);

        $this->actingAs($this->usuario([P::INTEGRACOES_VISUALIZAR]))->get(route('api-integrations.index'))
            ->assertOk()->assertSee('token antigo')->assertSee('192.168.50.4')->assertSee('HAL (IA)')->assertSee('api.guias.search');
    }

    public function test_item_integracoes_no_menu_so_para_quem_alcanca(): void
    {
        $url = str_replace('/', '\\/', route('api-integrations.index'));

        $com = $this->actingAs($this->usuario([P::INTEGRACOES_VISUALIZAR]))->get(route('docs.show', 'README'))->getContent();
        $this->assertTrue(str_contains($com, route('api-integrations.index')) || str_contains($com, $url));

        $sem = $this->actingAs($this->usuario([P::SIV_BUSCA]))->get(route('docs.show', 'README'))->getContent();
        $this->assertFalse(str_contains($sem, route('api-integrations.index')) || str_contains($sem, $url));
    }
}
