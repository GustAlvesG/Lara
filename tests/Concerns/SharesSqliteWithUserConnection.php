<?php

namespace Tests\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Faz a conexão do User (presa a `mysql`, ver o model) usar o MESMO banco
 * SQLite em memória da suíte, para testar com banco de verdade o que depende
 * de usuário: o cálculo do acesso e as travas do AccessManager.
 *
 * Por que é seguro, dado que a suíte já apagou o banco de homologação duas
 * vezes (ver tests/TestCase):
 *
 *   - só age se a conexão padrão for SQLite — senão aborta;
 *   - troca a CONFIGURAÇÃO da conexão `mysql` por uma cópia da SQLite antes
 *     de abri-la, e confere que a troca pegou: não existe caminho em que um
 *     driver MySQL seja instanciado;
 *   - reaproveita o PDO da conexão padrão, então as duas enxergam o mesmo
 *     banco em memória (duas conexões `:memory:` seriam dois bancos).
 *
 * Chame depois de migrar (ver MigratesAccessSchema).
 */
trait SharesSqliteWithUserConnection
{
    protected function shareSqliteWithUserConnection(): void
    {
        $default = config('database.default');

        if (config("database.connections.{$default}.driver") !== 'sqlite') {
            throw new RuntimeException('SharesSqliteWithUserConnection só roda com a conexão padrão em SQLite.');
        }

        $userConnection = (new User())->getConnectionName();

        config(["database.connections.{$userConnection}" => config("database.connections.{$default}")]);
        DB::purge($userConnection);

        $connection = DB::connection($userConnection);

        if ($connection->getDriverName() !== 'sqlite') {
            throw new RuntimeException("A conexão {$userConnection} não virou SQLite — abortando antes de tocar em qualquer banco.");
        }

        $connection->setPdo(DB::connection($default)->getPdo());
    }

    /** Cria um usuário direto na tabela e devolve o model. */
    protected function makeUser(string $name, array $attributes = []): User
    {
        $id = DB::table('users')->insertGetId(array_merge([
            'name' => $name,
            'email' => str($name)->slug() . '@teste.local',
            'password' => 'x',
            'status_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));

        return User::findOrFail($id);
    }

    protected function sectorId(string $name): int
    {
        return (int) DB::table('sectors')->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->value('id');
    }

    protected function joinSector(User $user, string $sector, string $role = 'collaborator'): void
    {
        DB::table('user_sector')->insert([
            'user_id' => $user->id,
            'sector_id' => $this->sectorId($sector),
            'role' => $role,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $user->forgetAccess();
    }
}
