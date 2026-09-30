<?php

namespace Tests\Concerns;

use App\Authorization\Permissions;
use App\Authorization\UserAccess;
use App\Models\User;
use Mockery;

/**
 * Usuário falso para os testes web do Placar Clube, sem nunca tocar a
 * tabela `sectors`/`user_sector`: o model User está preso à conexão mysql
 * (ver docs/models.md e a memória do projeto), e os testes só podem rodar
 * em SQLite — uma consulta de verdade nessas tabelas tentaria abrir a
 * conexão mysql do `.env` mesmo dentro da suíte.
 *
 * Por isso o mock intercepta `access()`, o acesso efetivo que o Gate::before
 * consulta para toda permissão do catálogo (rota, menu, abas). Renderizar o
 * layout completo (`x-app-layout`) também pergunta `isCoordinator()` (menu
 * "Meu setor") e `unreadNotifications()` em toda página, e essas precisam
 * ficar inofensivas também.
 */
trait MocksPlacarUser
{
    protected function usuarioDoSetorEsporte(bool $temAcesso = true): User
    {
        $user = Mockery::mock(User::class)->makePartial();

        $user->shouldReceive('access')->andReturn(
            $temAcesso ? new UserAccess([Permissions::PLACAR_CADASTRO, Permissions::PLACAR_SCOUT]) : UserAccess::none()
        );

        $user->shouldReceive('belongsToSectorNamed')
            ->andReturnUsing(fn (string $nome) => $temAcesso && $nome === User::SPORT_SECTOR);
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
