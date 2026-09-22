<?php

namespace Tests\Concerns;

use App\Models\User;
use Mockery;

/**
 * Usuário falso para os testes web do módulo de assinatura.
 *
 * Mesmo motivo de {@see MocksPlacarUser}: o model User está preso à conexão
 * `mysql` e os testes só rodam em SQLite — qualquer consulta de verdade
 * (permissões do Spatie, setores, notificações) tentaria abrir a conexão do
 * `.env` de dentro da suíte.
 *
 * Aqui o ponto interceptado é `can()`/`canAny()`, e não os métodos de setor:
 *
 *  - o middleware `permission:` do Spatie chama `canAny()`;
 *  - a Policy chama `can()`;
 *  - e o menu do layout, que renderiza em TODA tela, pergunta por `can()`
 *    para cada item — incluindo os Gates de freelancer, cotação e placar, que
 *    por baixo chamariam métodos do model que vão ao banco.
 *
 * `hasRole`, `isCoordinator` e `unreadNotifications` entram pelo mesmo motivo
 * registrado em MocksPlacarUser: são as três checagens do layout que não
 * passam por `can()`.
 */
trait MocksSignatureUser
{
    /**
     * @param  array<int, string>  $permissoes  nomes das permissões que este usuário tem
     */
    protected function usuarioComPermissoes(array $permissoes = []): User
    {
        $user = Mockery::mock(User::class)->makePartial();

        $user->shouldReceive('can')
            ->andReturnUsing(fn($ability) => in_array($ability, $permissoes, true));

        $user->shouldReceive('canAny')
            ->andReturnUsing(fn($abilities) => collect((array) $abilities)
                ->contains(fn($ability) => in_array($ability, $permissoes, true)));

        $user->shouldReceive('hasRole')->andReturn(false);
        $user->shouldReceive('isCoordinator')->andReturn(false);

        $semNotificacoes = Mockery::mock();
        $semNotificacoes->shouldReceive('latest')->andReturnSelf();
        $semNotificacoes->shouldReceive('limit')->andReturnSelf();
        $semNotificacoes->shouldReceive('get')->andReturn(collect());
        $user->shouldReceive('unreadNotifications')->andReturn($semNotificacoes);

        $user->id = 1;
        $user->name = 'Atendente de Teste';

        return $user;
    }
}
