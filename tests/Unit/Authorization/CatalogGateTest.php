<?php

namespace Tests\Unit\Authorization;

use App\Authorization\Permissions;
use App\Authorization\UserAccess;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Mockery;
use Tests\TestCase;

/**
 * O Gate::before do catálogo e o que ele NÃO pode fazer.
 *
 * Sem banco: o User é um mock que responde pelo acesso efetivo e pelos
 * cargos (ver tests/Concerns/MocksPlacarUser).
 */
class CatalogGateTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function usuario(UserAccess $access): User
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('access')->andReturn($access);
        $user->shouldReceive('isCoordinatorOfSectorNamed')->andReturn(false);
        $user->shouldReceive('isManagementCoordinator')->andReturn(false);
        $user->shouldReceive('isCoordinator')->andReturn(false);

        return $user;
    }

    public function test_permissao_do_catalogo_segue_o_acesso_efetivo(): void
    {
        $user = $this->usuario(new UserAccess([Permissions::SIV_BUSCA]));

        $this->assertTrue($user->can(Permissions::SIV_BUSCA));
        $this->assertFalse($user->can(Permissions::SIV_FROTA));
    }

    public function test_acesso_total_alcanca_todo_o_catalogo(): void
    {
        $user = $this->usuario(new UserAccess([], ['Gerência']));

        foreach (Permissions::all() as $permission) {
            $this->assertTrue($user->can($permission), $permission);
        }
    }

    /**
     * O acesso total vale para o catálogo, e só para ele: quem responde pelo
     * lote, valida contrato ou coordena "Meu setor" é quem tem o cargo.
     */
    public function test_acesso_total_nao_passa_por_cima_das_regras_de_cargo(): void
    {
        $user = $this->usuario(new UserAccess([], ['TI']));

        foreach (['validate-freelancer-contracts', 'manage-freelancer-director', 'coordinate-sector'] as $gate) {
            $this->assertFalse(Gate::forUser($user)->allows($gate), "{$gate} não pode vir do acesso total");
        }
    }

    public function test_habilidade_desconhecida_nao_e_liberada_pelo_acesso_total(): void
    {
        $user = $this->usuario(new UserAccess([], ['TI']));

        $this->assertFalse($user->can('qualquer-coisa-que-nao-existe'));
        $this->assertFalse($user->can('manage users'));
    }

    /** Um Gate de cargo com nome do catálogo seria atravessado pelo acesso total. */
    public function test_nenhum_gate_definido_tem_nome_do_catalogo(): void
    {
        foreach (array_keys(Gate::abilities()) as $ability) {
            $this->assertFalse(Permissions::exists($ability), "O Gate `{$ability}` colide com uma permissão do catálogo.");
        }
    }

    /**
     * Todo `can:` de rota aponta para algo que existe: permissão do catálogo
     * ou Gate definido. Um nome digitado errado daria 403 para todo mundo —
     * inclusive para o acesso total, que só cobre o catálogo.
     */
    public function test_toda_rota_protegida_usa_uma_habilidade_que_existe(): void
    {
        $checked = 0;

        foreach (Route::getRoutes()->getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $middleware) {
                if (! is_string($middleware) || ! str_starts_with($middleware, 'can:')) {
                    continue;
                }

                $ability = explode(',', substr($middleware, 4))[0];
                $checked++;

                $this->assertTrue(
                    Permissions::exists($ability) || Gate::has($ability),
                    "A rota {$route->getName()} exige `{$ability}`, que não é permissão do catálogo nem Gate."
                );
            }
        }

        $this->assertGreaterThan(100, $checked, 'Quase nenhuma rota com can: — o arquivo de rotas mudou?');
    }

    /** O que antes era aberto a qualquer login e agora tem porta. */
    public function test_telas_que_eram_abertas_agora_exigem_permissao(): void
    {
        $expected = [
            'schedule.index' => Permissions::RESERVAS_AGENDAMENTOS,
            'schedule.update' => Permissions::RESERVAS_AGENDAMENTOS,
            'parking-authorizations.index' => Permissions::SIV_PLACAS_DIRETORIA,
            'company.one-off.index' => Permissions::EXTERNOS_LIBERACAO_PONTUAL,
            'company.access.logs' => Permissions::EXTERNOS_HISTORICO,
            'company.uber.requests' => Permissions::EXTERNOS_CARROS_APLICATIVO,
            'company.uber.waiting' => Permissions::EXTERNOS_CARROS_APLICATIVO,
            'place-group.index' => Permissions::RESERVAS_CONFIGURAR,
            'tournaments.index' => Permissions::TORNEIOS,
            'members.index' => Permissions::SOCIOS_CONSULTA,
            'freelancer-services.index' => Permissions::FREELANCERS_SERVICOS_LISTAR,
            'freelancer-services.show' => Permissions::FREELANCERS_SERVICOS_GERENCIAR,
            'freelancer-services.document' => Permissions::FREELANCERS_SERVICOS_GERENCIAR,
            'kiosk.index' => null, // autenticação própria (matrícula + PIN)
        ];

        foreach ($expected as $name => $permission) {
            $middleware = Route::getRoutes()->getByName($name)->gatherMiddleware();

            if ($permission === null) {
                $this->assertEmpty(array_filter($middleware, fn ($m) => is_string($m) && str_starts_with($m, 'can:')), $name);
                continue;
            }

            $this->assertContains('can:' . $permission, $middleware, "{$name} deveria exigir {$permission}");
        }
    }

    /** Públicas por decisão: InfoClube, Avisos, Empresas e o Monitor. */
    public function test_telas_publicas_so_pedem_login(): void
    {
        foreach (['information.index', 'information.show', 'avisos.index', 'avisos.create', 'company.index', 'company.create', 'company.access.monitor', 'dashboard'] as $name) {
            $middleware = Route::getRoutes()->getByName($name)->gatherMiddleware();

            $this->assertContains('auth', $middleware, $name);
            $this->assertEmpty(array_filter($middleware, fn ($m) => is_string($m) && str_starts_with($m, 'can:')), "{$name} deveria ser pública");
        }
    }

    public function test_smart_panel_saiu_do_sistema(): void
    {
        $this->assertFalse(Route::has('videowall.index'));
    }
}
