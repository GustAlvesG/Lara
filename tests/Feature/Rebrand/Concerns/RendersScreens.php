<?php

namespace Tests\Feature\Rebrand\Concerns;

use App\Authorization\UserAccess;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Mockery;

/**
 * Renderiza telas repaginadas sem banco: usuário mock (o User está preso à
 * conexão mysql) e a requisição casada com a rota da tela, que é o que o
 * layout usa para achar a área, a capa e as abas.
 */
trait RendersScreens
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function usuario(UserAccess $access, string $name = 'Pessoa de Teste Silva'): User
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('access')->andReturn($access);
        $user->shouldReceive('isCoordinator')->andReturn(false);

        $semNotificacoes = Mockery::mock();
        $semNotificacoes->shouldReceive('latest')->andReturnSelf();
        $semNotificacoes->shouldReceive('limit')->andReturnSelf();
        $semNotificacoes->shouldReceive('get')->andReturn(collect());
        $user->shouldReceive('unreadNotifications')->andReturn($semNotificacoes);

        $user->id = 9;
        $user->name = $name;

        return $user;
    }

    /** Requisição já casada com a rota, como o layout a enxerga. */
    protected function requisicaoEm(string $routeName, array $params = [], array $query = []): Request
    {
        $route = Route::getRoutes()->getByName($routeName);
        $request = Request::create(route($routeName, $params), 'GET', $query);
        $route->bind($request);
        $request->setRouteResolver(fn () => $route);
        // Formulários usam old() e @error, que leem a sessão.
        $request->setLaravelSession($this->app['session.store']);
        $this->app['view']->share('errors', new \Illuminate\Support\ViewErrorBag);

        $this->app->instance('request', $request);
        $this->app['url']->setRequest($request);

        return $request;
    }

    /** A tela inteira (layout incluso), logada e na rota dela. */
    protected function tela(User $user, string $routeName, array $params, string $view, array $data, array $query = []): string
    {
        $this->actingAs($user);
        $this->requisicaoEm($routeName, $params, $query);

        return (string) $this->view($view, $data);
    }
}
