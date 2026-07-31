<?php

namespace Tests\Unit;

use App\Http\Middleware\EnsureMandatoryAvisosAcknowledged;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

/**
 * Avisos de leitura obrigatória: regras de desvio da navegação.
 *
 * Os casos aqui são justamente os que NÃO devem ser desviados — é onde mora o
 * risco de laço infinito (desviar a própria tela de ciência) e de quebra de
 * fluxo (desviar um POST em andamento ou o polling de notificações). Por isso o
 * middleware sequer consulta o banco nesses casos, e o teste roda sem banco.
 */
class MandatoryAvisoInterceptionTest extends TestCase
{
    private function handle(Request $request): Response
    {
        $middleware = new EnsureMandatoryAvisosAcknowledged();

        return $middleware->handle($request, fn () => new Response('seguiu'));
    }

    private function request(string $method, string $uri, ?string $routeName, bool $authenticated = true): Request
    {
        $request = Request::create($uri, $method);

        if ($routeName !== null) {
            $route = (new Route([$method], $uri, []))->name($routeName);
            $request->setRouteResolver(fn () => $route);
        }

        if ($authenticated) {
            $request->setUserResolver(fn () => new User());
        }

        return $request;
    }

    private function assertSeguiu(Response $response): void
    {
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('seguiu', $response->getContent());
    }

    public function test_a_tela_de_ciencia_nao_e_desviada_para_ela_mesma(): void
    {
        $this->assertSeguiu($this->handle(
            $this->request('GET', '/avisos/pendentes', 'avisos.pending')
        ));
    }

    public function test_o_logout_continua_acessivel_com_aviso_pendente(): void
    {
        $this->assertSeguiu($this->handle(
            $this->request('GET', '/logout', 'logout')
        ));
    }

    public function test_requisicoes_que_nao_sao_navegacao_nao_sao_desviadas(): void
    {
        // POST em andamento
        $this->assertSeguiu($this->handle(
            $this->request('POST', '/avisos', 'avisos.store')
        ));

        // Polling de notificações do layout (X-Requested-With)
        $ajax = $this->request('GET', '/notifications/unread-json', 'notifications.unreadJson');
        $ajax->headers->set('X-Requested-With', 'XMLHttpRequest');
        $this->assertSeguiu($this->handle($ajax));
    }

    public function test_visitante_nao_autenticado_nao_e_desviado(): void
    {
        $this->assertSeguiu($this->handle(
            $this->request('GET', '/dashboard', 'dashboard', authenticated: false)
        ));
    }

    public function test_o_middleware_esta_ligado_nas_rotas_autenticadas(): void
    {
        foreach (['dashboard', 'profile.edit', 'avisos.index'] as $name) {
            $route = RouteFacade::getRoutes()->getByName($name);

            $this->assertNotNull($route, "Rota {$name} não encontrada.");
            $this->assertContains(
                'avisos_obrigatorios',
                $route->gatherMiddleware(),
                "Rota {$name} ficou fora do bloqueio de leitura obrigatória."
            );
        }
    }
}
