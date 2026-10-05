<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Cria a permissão `manage replay`.
 *
 * Mesma razão da migration de `authorize purchase orders`: o
 * `RolesAndPermissionsSeeder` não roda no deploy, e rodá-lo em produção faria
 * muito mais do que criar uma permissão. Sem esta migration, o módulo sobe com
 * 403 para todo mundo — e o sintoma ("a permissão não aparece na tela de
 * papéis") não aponta para a causa.
 *
 * Concede a `admin` porque é o que o seeder faz. Quem mais recebe — o pessoal
 * do Marketing, no caso deste módulo — é decisão de operação, na tela de
 * Permissões.
 */
return new class extends Migration
{
    private const NAME = 'manage replay';

    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permission = Permission::firstOrCreate(
            ['name' => self::NAME, 'guard_name' => 'web'],
            ['description' => 'Permite configurar o Replay (formatos, layouts, câmeras) e administrar os vídeos'],
        );

        Role::where('name', 'admin')->first()?->givePermissionTo($permission);
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::where('name', self::NAME)->where('guard_name', 'web')->delete();
    }
};
