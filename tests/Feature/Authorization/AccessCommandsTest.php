<?php

namespace Tests\Feature\Authorization;

use App\Authorization\Permissions as P;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\Concerns\MigratesAccessSchema;
use Tests\Concerns\SharesSqliteWithUserConnection;
use Tests\TestCase;

/**
 * Os comandos da migração de roles para setores: o relatório de diferença, o
 * de-para (que só acrescenta) e a limpeza do legado (que só age com
 * --confirmar).
 */
class AccessCommandsTest extends TestCase
{
    use MigratesAccessSchema;
    use SharesSqliteWithUserConnection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateAccessSchema();
        $this->shareSqliteWithUserConnection();
    }

    /** Uma role antiga com permissões antigas, e o usuário nela. */
    private function legacyRole(string $role, array $permissions, int $userId): void
    {
        $roleId = DB::table('roles')->insertGetId(['name' => $role, 'guard_name' => 'web']);

        foreach ($permissions as $name) {
            $permissionId = DB::table('permissions')->where('name', $name)->value('id')
                ?? DB::table('permissions')->insertGetId(['name' => $name, 'guard_name' => 'web']);
            DB::table('role_has_permissions')->insert(['permission_id' => $permissionId, 'role_id' => $roleId]);
        }

        DB::table('model_has_roles')->insert(['role_id' => $roleId, 'model_type' => 'App\\Models\\User', 'model_id' => $userId]);
    }

    public function test_diferenca_mostra_quem_perde_ao_sair_da_role(): void
    {
        $user = $this->makeUser('Recepcionista', ['status_id' => null]);
        $this->legacyRole('secretaria', ['search parking'], $user->id);

        // Uma expectativa só: a linha da tabela traz nome e permissão juntos, e
        // cada expectsOutputToContain consome uma escrita da saída.
        $this->artisan('acesso:diferenca', ['--todos' => true, '--sem-abertas' => true])
            ->expectsOutputToContain(P::SIV_BUSCA)
            ->expectsOutputToContain('1 perdem algo')
            ->assertSuccessful();
    }

    public function test_de_para_simula_por_padrao_e_so_grava_com_aplicar(): void
    {
        $user = $this->makeUser('Recepcionista');
        $this->legacyRole('secretaria', ['search parking'], $user->id);

        $arquivo = storage_path('framework/testing-de-para.php');
        File::ensureDirectoryExists(dirname($arquivo));
        File::put($arquivo, "<?php return ['papeis' => ['secretaria' => ['setor' => 'atendimento', 'coordenadores' => []]], 'pessoas' => []];");

        try {
            $this->artisan('acesso:aplicar-de-para', ['--arquivo' => $arquivo])->assertSuccessful();
            $this->assertFalse($user->fresh()->can(P::SIV_BUSCA), 'simulação não pode gravar');

            $this->artisan('acesso:aplicar-de-para', ['--arquivo' => $arquivo, '--aplicar' => true])->assertSuccessful();

            $user = $user->fresh();
            $this->assertTrue($user->can(P::SIV_BUSCA));
            $this->assertDatabaseHas('access_audit_logs', ['action' => 'sector.member_added', 'user_id' => $user->id]);
        } finally {
            File::delete($arquivo);
        }
    }

    public function test_de_para_nao_rebaixa_coordenador(): void
    {
        $user = $this->makeUser('Coordenadora');
        $this->joinSector($user, 'Atendimento', 'coordinator');
        $this->legacyRole('secretaria', [], $user->id);

        $arquivo = storage_path('framework/testing-de-para.php');
        File::put($arquivo, "<?php return ['papeis' => ['secretaria' => ['setor' => 'Atendimento']]];");

        try {
            $this->artisan('acesso:aplicar-de-para', ['--arquivo' => $arquivo, '--aplicar' => true])->assertSuccessful();
        } finally {
            File::delete($arquivo);
        }

        $this->assertSame('coordinator', DB::table('user_sector')->where('user_id', $user->id)->value('role'));
    }

    public function test_limpar_legado_so_apaga_com_confirmar_e_preserva_o_catalogo(): void
    {
        $user = $this->makeUser('Antigo');
        $this->legacyRole('secretaria', ['search parking'], $user->id);

        $this->artisan('acesso:limpar-legado')->assertSuccessful();
        $this->assertSame(1, DB::table('roles')->count());

        $this->artisan('acesso:limpar-legado', ['--confirmar' => true])->assertSuccessful();

        $this->assertSame(0, DB::table('roles')->count());
        $this->assertSame(0, DB::table('model_has_roles')->count());
        $this->assertFalse(DB::table('permissions')->where('name', 'search parking')->exists());
        $this->assertSame(count(P::all()), DB::table('permissions')->whereIn('name', P::all())->count());
    }
}
