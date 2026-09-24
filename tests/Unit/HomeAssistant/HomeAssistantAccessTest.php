<?php

namespace Tests\Unit\HomeAssistant;

use App\Http\Controllers\PlaceGroupController;
use App\Models\Place;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Quem pode mexer na iluminação.
 *
 * A permissão `manage home assistant` só escondia o menu e o card do dashboard:
 * as rotas do painel exigiam apenas login, e o contator de um espaço era gravado
 * a partir do que chegasse no POST. Sem banco — o User é mockado (ver
 * user-model preso à conexão mysql em MocksPlacarUser).
 */
class HomeAssistantAccessTest extends TestCase
{
    public function test_todas_as_rotas_do_painel_exigem_a_permissao(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with((string) $route->getName(), 'home-assistant.'));

        // index, contator (3), ação rápida (2), agendamento (4) e o
        // autoatendimento (5: horário padrão, horário por quadra, reset, e as
        // duas de datas) — quem mexe nesses horários decide quando o clube
        // inteiro pode acender a luz, e isso não é para qualquer login.
        $this->assertCount(15, $routes, 'Rota nova no painel? Confira se ela está no grupo com a permissão.');

        foreach ($routes as $route) {
            $this->assertContains(
                'permission:manage home assistant',
                $route->gatherMiddleware(),
                "A rota {$route->getName()} não exige a permissão manage home assistant."
            );
        }
    }

    public function test_endpoint_do_home_assistant_exige_o_token_da_api(): void
    {
        $middleware = Route::getRoutes()->getByName('api.schedule.homeAssistantAutomation')->gatherMiddleware();

        $this->assertContains('api_token', $middleware);
    }

    private function contactorIdFor(bool $canManage, array $input, ?Place $place = null): ?int
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('can')->with('manage home assistant')->andReturn($canManage);

        $request = Request::create('/place', 'POST', $input);
        $request->setUserResolver(fn () => $user);

        $method = new ReflectionMethod(PlaceGroupController::class, 'contactorIdFor');

        return $method->invoke(app(PlaceGroupController::class), $request, $place);
    }

    public function test_sem_permissao_o_contator_do_espaco_nao_muda(): void
    {
        $place = (new Place())->forceFill(['contactor_id' => 3]);

        $this->assertSame(3, $this->contactorIdFor(false, ['contactor_id' => 7], $place));
        $this->assertSame(3, $this->contactorIdFor(false, ['contactor_id' => ''], $place));
    }

    public function test_sem_permissao_espaco_novo_nasce_sem_contator(): void
    {
        $this->assertNull($this->contactorIdFor(false, ['contactor_id' => 7]));
    }

    public function test_com_permissao_o_contator_pode_ser_trocado_ou_removido(): void
    {
        $place = (new Place())->forceFill(['contactor_id' => 3]);

        $this->assertSame(7, $this->contactorIdFor(true, ['contactor_id' => '7'], $place));
        $this->assertNull($this->contactorIdFor(true, ['contactor_id' => ''], $place));
        // Campo ausente (select desabilitado não é enviado): mantém
        $this->assertSame(3, $this->contactorIdFor(true, [], $place));
    }
}
