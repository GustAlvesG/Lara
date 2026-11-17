<?php

namespace Tests\Feature\ApiTokens;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * F3 da gestão de tokens: cada grupo de routes/api.php pede o escopo do
 * sistema que o consome. Se uma rota mudar de grupo (ou perder o token),
 * aparece aqui. Inventário completo em docs/funcionalidades/gestao-tokens.md.
 */
class ApiRouteScopesTest extends TestCase
{
    /** @return array<string, array{0: string, 1: string}> */
    public static function rotas(): array
    {
        return [
            'busca de informações (IA)' => ['api.information.search', 'api_token:ia'],
            'busca de avisos (IA)' => ['api.avisos.search', 'api_token:ia'],
            'busca de guias (IA)' => ['api.guias.search', 'api_token:ia'],
            'placas (portaria)' => ['api.parking.check', 'api_token:portaria'],
            'frota (portaria)' => ['api.fleet.departure', 'api_token:portaria'],
            'monitor de acesso (portaria, registrando)' => ['company_access.validate', 'api_token_portaria:portaria'],
            'check-in de ingresso (portaria, registrando)' => ['company_access.register_ticket', 'api_token_portaria:portaria'],
            'automação do HA' => ['api.schedule.homeAssistantAutomation', 'api_token:home-assistant'],
            'comando manual do HA' => ['api.home-assistant.manual', 'api_token:home-assistant,telegram'],
            'webhook da Poli' => ['api.webhooks.whatsapp', 'api_token:webhook-whatsapp'],
            'login do Telegram' => ['telegram.userLogin', 'api_token:telegram'],
            'vitrine de torneios (site)' => ['api.tournaments.index', 'api_token:site'],
            'ingressos (site)' => ['api.tickets.events.index', 'api_token:site'],
            'galeria do replay (site)' => ['api.replay.places', 'api_token:site'],
            'dashboards (site)' => ['api.dashboard.access', 'api_token:site'],
            'iluminação do sócio (site)' => ['api.lighting.activate', 'api_token:site'],
        ];
    }

    #[DataProvider('rotas')]
    public function test_rota_pede_o_escopo_do_sistema(string $name, string $expected): void
    {
        $route = Route::getRoutes()->getByName($name);
        $this->assertNotNull($route, "Rota {$name} não existe");

        $tokens = array_values(array_filter(
            $route->gatherMiddleware(),
            fn ($m) => is_string($m) && str_starts_with($m, 'api_token'),
        ));

        $this->assertSame([$expected], $tokens, "{$name} deveria pedir só {$expected}");
    }

    public function test_nenhuma_rota_da_api_usa_api_token_sem_escopo(): void
    {
        foreach (Route::getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $middleware) {
                if (is_string($middleware) && in_array($middleware, ['api_token', 'api_token_portaria'], true)) {
                    $this->fail("{$route->uri()} usa {$middleware} sem escopo: nenhum token gerenciado passaria.");
                }
            }
        }
        $this->addToAssertionCount(1);
    }
}
