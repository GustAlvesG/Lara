<?php

namespace Tests\Feature\Authorization;

use App\Authorization\Permissions as P;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\MigratesAccessSchema;
use Tests\Concerns\SharesSqliteWithUserConnection;
use Tests\TestCase;

/**
 * A migration que liga a reforma: catálogo completo, setores criados sem
 * duplicar os que já existem com outra caixa, e as duas pontes (permissão
 * individual antiga e administradores) — rodando de novo sem estragar nada.
 */
class SyncAccessCatalogMigrationTest extends TestCase
{
    use MigratesAccessSchema;
    use SharesSqliteWithUserConnection;

    private const MIGRATION = '2026_09_30_100300_sync_access_catalog.php';

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateAccessSchema();
        $this->shareSqliteWithUserConnection();
    }

    private function runMigration(): void
    {
        (require database_path('migrations/' . self::MIGRATION))->up();
    }

    public function test_o_catalogo_inteiro_existe_na_tabela(): void
    {
        $names = DB::table('permissions')->where('guard_name', 'web')->pluck('name')->all();

        foreach (P::all() as $permission) {
            $this->assertContains($permission, $names);
        }
    }

    public function test_rodar_de_novo_nao_duplica_nem_desfaz_o_que_a_tela_mudou(): void
    {
        $portaria = $this->sectorId('Portaria');
        $frota = DB::table('permissions')->where('name', P::SIV_FROTA)->value('id');

        // Alguém tirou a frota da Portaria na tela.
        DB::table('sector_permission')->where('sector_id', $portaria)->where('permission_id', $frota)->delete();

        $this->runMigration();

        $this->assertSame(1, DB::table('sectors')->whereRaw('LOWER(name) = ?', ['portaria'])->count());
        $this->assertSame(1, DB::table('permissions')->where('name', P::LARA)->count());
        // insertOrIgnore: a linha apagada volta. É o custo de ser idempotente —
        // a migration não sabe o que a tela fez. Por isso ela roda uma vez só
        // por ambiente, como toda migration.
        $this->assertTrue(DB::table('sector_permission')->where('sector_id', $portaria)->where('permission_id', $frota)->exists());
    }

    public function test_setor_existente_com_outra_caixa_e_reaproveitado(): void
    {
        // Em homologação o setor existe como "comercial".
        DB::table('sectors')->where('id', $this->sectorId('Comercial'))->update(['name' => 'comercial']);

        $this->runMigration();

        $this->assertSame(1, DB::table('sectors')->whereRaw('LOWER(name) = ?', ['comercial'])->count());
    }

    public function test_financas_recebe_o_mesmo_que_a_contabilidade(): void
    {
        $financas = DB::table('sectors')->insertGetId(['name' => 'Finanças', 'created_at' => now(), 'updated_at' => now()]);

        $this->runMigration();

        $user = $this->makeUser('Financeira');
        DB::table('user_sector')->insert(['user_id' => $user->id, 'sector_id' => $financas, 'role' => 'collaborator']);

        $this->assertTrue($user->can(P::COMPRAS));
        $this->assertTrue($user->can(P::FREELANCERS_FINANCEIRO));
    }

    public function test_permissao_individual_antiga_vira_a_nova(): void
    {
        $user = $this->makeUser('Nominal do RH');
        $legacy = DB::table('permissions')->insertGetId(['name' => 'import comp time', 'guard_name' => 'web']);
        DB::table('model_has_permissions')->insert(['permission_id' => $legacy, 'model_type' => 'App\\Models\\User', 'model_id' => $user->id]);

        $this->runMigration();

        $this->assertTrue($user->can(P::BANCO_HORAS_ADMIN));
        $this->assertFalse($user->can(P::CARTEIRINHAS));
    }

    /** Sem a ponte, no dia do deploy ninguém alcançaria a tela de Setores. */
    public function test_quem_tinha_a_role_admin_ganha_as_telas_de_gestao_mas_nao_acesso_total(): void
    {
        $user = $this->makeUser('Admin Antigo');
        $role = DB::table('roles')->insertGetId(['name' => 'admin', 'guard_name' => 'web']);
        DB::table('model_has_roles')->insert(['role_id' => $role, 'model_type' => 'App\\Models\\User', 'model_id' => $user->id]);

        $this->runMigration();

        $this->assertTrue($user->can(P::USUARIOS_GERENCIAR));
        $this->assertTrue($user->can(P::SETORES_GERENCIAR));
        $this->assertFalse($user->hasFullAccess());
        $this->assertFalse($user->can(P::LARA));
    }
}
