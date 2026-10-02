<?php

namespace Tests\Feature\Authorization;

use App\Authorization\Permissions as P;
use App\Authorization\UserAccess;
use App\Models\User;
use Mockery;
use Tests\TestCase;

/**
 * O menu renderizado de verdade (layout completo, pela tela de documentação,
 * que não consulta banco): cada item aparece só para quem a rota dele deixa
 * entrar, e o grupo some quando não sobra filho.
 *
 * O usuário é um mock — o User está preso à conexão mysql (ver
 * MocksPlacarUser).
 */
class NavigationMenuTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function usuario(UserAccess $access, bool $coordenador = false): User
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('access')->andReturn($access);
        $user->shouldReceive('isCoordinator')->andReturn($coordenador);

        $semNotificacoes = Mockery::mock();
        $semNotificacoes->shouldReceive('latest')->andReturnSelf();
        $semNotificacoes->shouldReceive('limit')->andReturnSelf();
        $semNotificacoes->shouldReceive('get')->andReturn(collect());
        $user->shouldReceive('unreadNotifications')->andReturn($semNotificacoes);

        $user->id = 9;
        $user->name = 'Pessoa de Teste';

        return $user;
    }

    private function menuDe(User $user): string
    {
        return $this->actingAs($user)->get(route('docs.show', 'README'))->assertOk()->getContent();
    }

    public function test_sem_permissao_so_ve_o_que_e_publico(): void
    {
        $html = $this->menuDe($this->usuario(UserAccess::none()));

        $this->assertStringContainsString(route('information.index'), $html);
        $this->assertStringContainsString(route('avisos.index'), $html);
        $this->assertStringContainsString(route('company.index'), $html);
        $this->assertStringContainsString(route('company.access.monitor'), $html);

        foreach (['parking.search', 'fleet.index', 'schedule.index', 'payment.index', 'lara.index', 'freelancers.index',
                  'company.one-off.index', 'company.access.logs', 'company.uber.requests', 'users.index', 'sectors.index', 'my-sector.index'] as $route) {
            $this->assertStringNotContainsString(route($route), $html, "{$route} não deveria aparecer");
        }
    }

    public function test_portaria_ve_o_siv_da_frota_e_os_externos_dela(): void
    {
        $html = $this->menuDe($this->usuario(new UserAccess([
            P::SIV_BUSCA, P::SIV_FROTA, P::SIV_VIAGENS, P::SIV_VEICULOS, P::EXTERNOS_HISTORICO, P::EXTERNOS_CARROS_APLICATIVO,
        ])));

        foreach (['parking.search', 'fleet.index', 'fleet.trips', 'fleet.vehicles', 'company.access.logs', 'company.uber.requests'] as $route) {
            $this->assertStringContainsString(route($route), $html, "{$route} deveria aparecer");
        }

        $this->assertStringNotContainsString(route('parking-authorizations.index'), $html);
        $this->assertStringNotContainsString(route('company.one-off.index'), $html);
    }

    public function test_pagamentos_e_sub_aba_de_reservas(): void
    {
        // Só pagamentos (o Financeiro): o grupo Reservas aparece com ela sozinha.
        $html = $this->menuDe($this->usuario(new UserAccess([P::RESERVAS_PAGAMENTOS])));

        $this->assertStringContainsString(route('payment.index'), $html);
        $this->assertStringContainsString('Reservas', $html);
        $this->assertStringNotContainsString(route('schedule.index'), $html);
    }

    public function test_aguardando_motorista_e_smart_panel_nao_estao_no_menu(): void
    {
        $html = $this->menuDe($this->usuario(new UserAccess([], ['TI'])));

        $this->assertStringNotContainsString('Aguardando Motorista', $html);
        $this->assertStringNotContainsString('Smart Panel', $html);
        // Carros de Aplicativo continua — a fila abre por dentro dele.
        $this->assertStringContainsString(route('company.uber.requests'), $html);
    }

    public function test_acesso_total_ve_as_telas_de_gestao(): void
    {
        $html = $this->menuDe($this->usuario(new UserAccess([], ['TI'])));

        $this->assertStringContainsString(route('users.index'), $html);
        $this->assertStringContainsString(route('sectors.index'), $html);
        $this->assertStringContainsString(route('lara.index'), $html);
    }

    public function test_coordenador_ve_meu_setor(): void
    {
        $html = $this->menuDe($this->usuario(UserAccess::none(), coordenador: true));

        $this->assertStringContainsString(route('my-sector.index'), $html);
        $this->assertStringNotContainsString(route('users.index'), $html);
    }

    public function test_rota_protegida_responde_403_sem_a_permissao(): void
    {
        $this->actingAs($this->usuario(new UserAccess([P::SIV_BUSCA])))
            ->get(route('fleet.index'))
            ->assertForbidden();
    }
}
