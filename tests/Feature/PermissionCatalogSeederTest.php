<?php

namespace Tests\Feature;

use App\Authorization\Permissions;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * O seed padrão de permissões, que o deploy roda toda vez.
 *
 * O que ele precisa garantir é o que o CLAUDE.md pede: a permissão nova passa
 * a existir no banco — e nada do que já estava configurado muda.
 */
class PermissionCatalogSeederTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        (require base_path('database/migrations/2025_12_23_111918_create_permission_tables.php'))->up();
        (require base_path('database/migrations/2025_12_24_091203_description_permission.php'))->up();
    }

    public function test_cria_todo_o_catalogo_inclusive_as_permissoes_de_assinatura(): void
    {
        $this->seed(PermissionCatalogSeeder::class);

        $nomes = DB::table('permissions')->where('guard_name', 'web')->pluck('name')->all();

        $this->assertEqualsCanonicalizing(Permissions::all(), $nomes);

        foreach ([
            Permissions::ASSINATURA_MODELOS,
            Permissions::ASSINATURA_DOCUMENTOS,
            Permissions::ASSINATURA_CONSULTAR,
            Permissions::ASSINATURA_EVIDENCIAS,
        ] as $assinatura) {
            $this->assertContains($assinatura, $nomes);
        }

        $this->assertSame(
            'Assinaturas — ' . Permissions::label(Permissions::ASSINATURA_DOCUMENTOS),
            DB::table('permissions')->where('name', Permissions::ASSINATURA_DOCUMENTOS)->value('description'),
        );
    }

    public function test_nao_da_permissao_a_ninguem(): void
    {
        $this->seed(PermissionCatalogSeeder::class);

        $this->assertSame(0, DB::table('model_has_permissions')->count());
        $this->assertSame(0, DB::table('role_has_permissions')->count());

        if (Schema::hasTable('sector_permission')) {
            $this->assertSame(0, DB::table('sector_permission')->count());
        }
    }

    public function test_rodar_de_novo_nao_duplica_nem_mexe_no_que_ja_existe(): void
    {
        // Um banco que já tinha uma permissão do catálogo dada a uma pessoa e uma permissão antiga, fora dele.
        $existente = DB::table('permissions')->insertGetId([
            'name' => Permissions::ASSINATURA_CONSULTAR, 'guard_name' => 'web', 'description' => 'rótulo antigo',
        ]);
        $legada = DB::table('permissions')->insertGetId(['name' => 'view signed documents', 'guard_name' => 'web']);

        DB::table('model_has_permissions')->insert([
            'permission_id' => $existente, 'model_type' => 'App\\Models\\User', 'model_id' => 7,
        ]);

        $this->seed(PermissionCatalogSeeder::class);
        $this->seed(PermissionCatalogSeeder::class);

        // Nada duplicado; a que já existia segue com o mesmo id e com quem a tinha.
        $this->assertSame(count(Permissions::all()) + 1, DB::table('permissions')->count());
        $this->assertSame($existente, DB::table('permissions')->where('name', Permissions::ASSINATURA_CONSULTAR)->value('id'));
        $this->assertSame(1, DB::table('model_has_permissions')->where('permission_id', $existente)->where('model_id', 7)->count());

        // Só a descrição acompanha o catálogo.
        $this->assertSame(
            'Assinaturas — ' . Permissions::label(Permissions::ASSINATURA_CONSULTAR),
            DB::table('permissions')->where('id', $existente)->value('description'),
        );

        // Permissão fora do catálogo não é apagada pelo deploy.
        $this->assertTrue(DB::table('permissions')->where('id', $legada)->exists());
    }
}
