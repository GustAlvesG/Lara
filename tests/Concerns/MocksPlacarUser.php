<?php

namespace Tests\Concerns;

use App\Models\User;
use Mockery;

/**
 * Usuário falso para os testes web do Placar Clube, sem nunca tocar a
 * tabela `sectors`/`user_sector`: o model User está preso à conexão mysql
 * (ver docs/models.md e a memória do projeto), e os testes só podem rodar
 * em SQLite — uma consulta de verdade nessas tabelas tentaria abrir a
 * conexão mysql do `.env` mesmo dentro da suíte.
 *
 * Por isso o mock intercepta o ponto certo (`belongsToSectorNamed`), não
 * `canAccessPlacar()`: renderizar o layout completo (`x-app-layout`) também
 * chama `canManageFreelancerPayments()`/`hasRole()`/`unreadNotifications()`
 * em toda página, e essas três precisam ficar inofensivas também.
 *
 * `isCoordinator()` entrou na lista pelo mesmo motivo: o menu do Banco de
 * Horas pergunta se a pessoa coordena algum setor, e essa é a única checagem
 * de setor do layout que não passa por `belongsToSectorNamed`.
 */
trait MocksPlacarUser
{
    protected function usuarioDoSetorEsporte(bool $temAcesso = true): User
    {
        $user = Mockery::mock(User::class)->makePartial();

        $user->shouldReceive('belongsToSectorNamed')
            ->andReturnUsing(fn (string $nome) => $temAcesso && $nome === User::SPORT_SECTOR);

        $user->shouldReceive('hasRole')->andReturn(false);
        $user->shouldReceive('isCoordinator')->andReturn(false);

        $semNotificacoes = Mockery::mock();
        $semNotificacoes->shouldReceive('latest')->andReturnSelf();
        $semNotificacoes->shouldReceive('limit')->andReturnSelf();
        $semNotificacoes->shouldReceive('get')->andReturn(collect());
        $user->shouldReceive('unreadNotifications')->andReturn($semNotificacoes);

        $user->id = 1;
        $user->name = 'Usuário de Teste';

        return $user;
    }
}
