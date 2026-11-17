<?php

namespace Tests\Feature\ApiTokens;

use App\Models\ApiTokens\ApiIntegration;
use App\Models\ApiTokens\ApiIntegrationToken;
use App\Services\ApiTokens\ApiTokenManager;
use App\Support\ApiTokens\ApiScopes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Gestão de tokens da API: o `api_token:<escopo>` aceita o token da
 * integração com o escopo da rota (e grava o último uso), o API_TOKEN antigo
 * enquanto ligado (registrado como legado), e o `api_token_portaria` só
 * registra até ser ligado. Rotas de teste próprias, para não depender do
 * schema dos módulos.
 */
class ApiTokenMiddlewareTest extends TestCase
{
    private const LEGACY = 'token-antigo-do-env';

    private ApiTokenManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        (require database_path('migrations/2026_11_17_100000_create_api_integrations_tables.php'))->up();

        config([
            'services.api.token' => self::LEGACY,
            'api_tokens.legacy_enabled' => true,
            'api_tokens.portaria_enforced' => false,
            'api_tokens.touch_seconds' => 60,
        ]);

        Route::middleware('api_token:' . ApiScopes::IA)->get('/_teste/ia', fn () => 'ok-ia')->name('teste.ia');
        Route::middleware('api_token:' . ApiScopes::HOME_ASSISTANT . ',' . ApiScopes::TELEGRAM)->get('/_teste/ha', fn () => 'ok-ha');
        Route::middleware('api_token_portaria:' . ApiScopes::PORTARIA)->get('/_teste/portaria', fn () => 'ok-portaria')->name('teste.portaria');

        $this->manager = app(ApiTokenManager::class);
    }

    private function integration(bool $active = true): ApiIntegration
    {
        return ApiIntegration::create(['name' => 'HAL ' . uniqid(), 'system' => 'ia', 'active' => $active]);
    }

    /** @return array{token: ApiIntegrationToken, plain: string} */
    private function token(array $scopes = [ApiScopes::IA], ?Carbon $expires = null, bool $active = true): array
    {
        return $this->manager->generate($this->integration($active), 'produção', $scopes, $expires);
    }

    private function hit(string $uri, ?string $token, string $ip = '10.0.0.5')
    {
        $headers = $token === null ? [] : ['Authorization' => 'Bearer ' . $token];

        return $this->withServerVariables(['REMOTE_ADDR' => $ip])->getJson($uri, $headers);
    }

    public function test_token_com_o_escopo_passa_e_grava_o_ultimo_uso(): void
    {
        Carbon::setTestNow('2026-11-17 10:00:00');
        $t = $this->token();

        $this->hit('/_teste/ia', $t['plain'])->assertOk()->assertSee('ok-ia');

        $token = $t['token']->fresh();
        $this->assertSame('2026-11-17 10:00:00', $token->last_used_at->toDateTimeString());
        $this->assertSame('10.0.0.5', $token->last_used_ip);
        $this->assertSame('teste.ia', $token->last_used_route);

        $row = DB::table('api_token_usage_daily')->where('api_integration_token_id', $token->id)->first();
        $this->assertSame('token', $row->kind);
        $this->assertSame(1, (int) $row->hits);
    }

    public function test_ultimo_uso_e_gravado_no_maximo_uma_vez_por_minuto_mas_conta_toda_chamada(): void
    {
        Carbon::setTestNow('2026-11-17 10:00:00');
        $t = $this->token();
        $this->hit('/_teste/ia', $t['plain'])->assertOk();

        Carbon::setTestNow('2026-11-17 10:00:30');
        $this->hit('/_teste/ia', $t['plain'])->assertOk();
        $this->assertSame('2026-11-17 10:00:00', $t['token']->fresh()->last_used_at->toDateTimeString());

        Carbon::setTestNow('2026-11-17 10:01:10');
        $this->hit('/_teste/ia', $t['plain'])->assertOk();
        $this->assertSame('2026-11-17 10:01:10', $t['token']->fresh()->last_used_at->toDateTimeString());

        $this->assertSame(3, (int) DB::table('api_token_usage_daily')->where('api_integration_token_id', $t['token']->id)->sum('hits'));
    }

    public function test_ip_diferente_atualiza_o_ultimo_uso_na_hora(): void
    {
        Carbon::setTestNow('2026-11-17 10:00:00');
        $t = $this->token();
        $this->hit('/_teste/ia', $t['plain'], '10.0.0.5')->assertOk();

        Carbon::setTestNow('2026-11-17 10:00:20');
        $this->hit('/_teste/ia', $t['plain'], '10.0.0.9')->assertOk();

        $this->assertSame('10.0.0.9', $t['token']->fresh()->last_used_ip);
    }

    public function test_escopo_errado_da_403_e_nao_mexe_no_ultimo_uso(): void
    {
        $t = $this->token([ApiScopes::SITE]);

        $this->hit('/_teste/ia', $t['plain'])->assertStatus(403);

        $this->assertNull($t['token']->fresh()->last_used_at);
        $this->assertSame('recusado', DB::table('api_token_usage_daily')->value('kind'));
    }

    public function test_rota_com_dois_escopos_aceita_qualquer_um(): void
    {
        $this->hit('/_teste/ha', $this->token([ApiScopes::TELEGRAM])['plain'])->assertOk();
        $this->hit('/_teste/ha', $this->token([ApiScopes::HOME_ASSISTANT])['plain'])->assertOk();
        $this->hit('/_teste/ha', $this->token([ApiScopes::IA])['plain'])->assertStatus(403);
    }

    public function test_revogado_vencido_desativado_e_desconhecido_dao_401(): void
    {
        $revoked = $this->token();
        $this->manager->revoke($revoked['token']);
        $this->hit('/_teste/ia', $revoked['plain'])->assertStatus(401);

        Carbon::setTestNow('2026-11-17 10:00:00');
        $expiring = $this->token(expires: now()->addDay());
        $this->hit('/_teste/ia', $expiring['plain'])->assertOk();
        Carbon::setTestNow('2026-11-18 10:00:01');
        $this->hit('/_teste/ia', $expiring['plain'])->assertStatus(401);

        $inactive = $this->token(active: false);
        $this->hit('/_teste/ia', $inactive['plain'])->assertStatus(401);

        $this->hit('/_teste/ia', ApiTokenManager::PREFIX . str_repeat('x', 48))->assertStatus(401);
    }

    public function test_sem_token_da_401(): void
    {
        $this->hit('/_teste/ia', null)->assertStatus(401)->assertJsonPath('message', 'Invalid API Token');
    }

    public function test_token_antigo_passa_enquanto_ligado_e_fica_registrado_como_legado(): void
    {
        $this->hit('/_teste/ia', self::LEGACY, '192.168.10.20')->assertOk();

        $row = DB::table('api_token_usage_daily')->first();
        $this->assertSame('legado', $row->kind);
        $this->assertSame('ia', $row->scope);
        $this->assertSame('192.168.10.20', $row->ip);
        $this->assertSame('teste.ia', $row->last_route);

        config(['api_tokens.legacy_enabled' => false]);
        $this->hit('/_teste/ia', self::LEGACY)->assertStatus(401);
    }

    public function test_token_antigo_vazio_no_env_nunca_passa(): void
    {
        config(['services.api.token' => '']);
        $this->withHeaders(['Authorization' => 'Bearer '])->getJson('/_teste/ia')->assertStatus(401);
    }

    public function test_falha_ao_registrar_o_uso_nao_derruba_a_chamada(): void
    {
        DB::statement('DROP TABLE api_token_usage_daily');

        $this->hit('/_teste/ia', self::LEGACY)->assertOk();
    }

    public function test_portaria_sem_token_passa_e_fica_registrada_ate_ser_ligada(): void
    {
        $this->hit('/_teste/portaria', null)->assertOk()->assertSee('ok-portaria');
        $this->assertSame('sem-token', DB::table('api_token_usage_daily')->value('kind'));

        $t = $this->token([ApiScopes::PORTARIA]);
        $this->hit('/_teste/portaria', $t['plain'])->assertOk();
        $this->assertNotNull($t['token']->fresh()->last_used_at);

        config(['api_tokens.portaria_enforced' => true]);
        $this->hit('/_teste/portaria', null)->assertStatus(401);
        $this->hit('/_teste/portaria', $this->token([ApiScopes::IA])['plain'])->assertStatus(403);
        $this->hit('/_teste/portaria', $t['plain'])->assertOk();
    }

    public function test_so_o_hash_fica_no_banco(): void
    {
        $t = $this->token();

        $this->assertStringStartsWith('lara_', $t['plain']);
        $this->assertSame(53, strlen($t['plain']));
        $this->assertSame(hash('sha256', $t['plain']), DB::table('api_integration_tokens')->value('token_hash'));
        $this->assertSame('…' . substr($t['plain'], -4), $t['token']->token_hint);
        $this->assertSame(0, DB::table('api_integration_tokens')->where('token_hash', $t['plain'])->count());
    }

    public function test_geracao_exige_escopo_conhecido_e_validade_futura(): void
    {
        $integration = $this->integration();

        foreach ([[[], null], [['inexistente'], null], [[ApiScopes::IA], now()->subDay()]] as [$scopes, $expires]) {
            try {
                $this->manager->generate($integration, 'produção', $scopes, $expires);
                $this->fail('Deveria recusar');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_rotacionar_mantem_o_antigo_pelos_dias_de_convivencia(): void
    {
        Carbon::setTestNow('2026-11-17 10:00:00');
        $old = $this->token([ApiScopes::IA, ApiScopes::SITE]);

        $new = $this->manager->rotate($old['token'], now()->addDays(90), 7);

        $this->assertSame($old['token']->name, $new['token']->name);
        $this->assertSame([ApiScopes::SITE, ApiScopes::IA], $new['token']->scopes);
        $fresh = $old['token']->fresh();
        $this->assertSame($new['token']->id, $fresh->replaced_by_id);
        $this->assertSame('2026-11-24 10:00:00', $fresh->expires_at->toDateTimeString());

        $this->hit('/_teste/ia', $old['plain'])->assertOk();
        $this->hit('/_teste/ia', $new['plain'])->assertOk();

        Carbon::setTestNow('2026-11-24 10:00:01');
        $this->hit('/_teste/ia', $old['plain'])->assertStatus(401);
        $this->hit('/_teste/ia', $new['plain'])->assertOk();
    }

    public function test_rotacionar_sem_convivencia_revoga_o_antigo_na_hora(): void
    {
        $old = $this->token();

        $new = $this->manager->rotate($old['token'], null, 0);

        $this->assertTrue($old['token']->fresh()->isRevoked());
        $this->hit('/_teste/ia', $old['plain'])->assertStatus(401);
        $this->hit('/_teste/ia', $new['plain'])->assertOk();
    }

    public function test_convivencia_nao_estica_validade_menor(): void
    {
        Carbon::setTestNow('2026-11-17 10:00:00');
        $old = $this->token(expires: now()->addDays(2));

        $this->manager->rotate($old['token'], null, 7);

        $this->assertSame('2026-11-19 10:00:00', $old['token']->fresh()->expires_at->toDateTimeString());
    }
}
