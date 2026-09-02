<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Permissão `import comp time`.
 *
 * A segunda metade da regra de acesso ao Banco de Horas: o Gate
 * `manage-comp-time` libera quem está no setor **RH** (qualquer papel) OU quem
 * tem esta permissão. Ela existe para o caso nominal — dar o acesso a uma
 * pessoa específica sem precisar colocá-la no RH.
 *
 * Aqui ela nasce na role `admin`, que é como o resto do app distribui
 * permissão. Se a intenção for restringir a uma pessoa só, tire da role e dê
 * ao usuário: `php artisan comp-time:grant-import <email> --revoke-role`.
 */
return new class extends Migration
{
    private const PERMISSION = 'import comp time';

    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permission = Permission::firstOrCreate(
            ['name' => self::PERMISSION, 'guard_name' => 'web'],
            ['description' => 'Permite importar o espelho de ponto e administrar o cadastro do Banco de Horas']
        );

        $admin = Role::where('name', 'admin')->where('guard_name', 'web')->first();
        if ($admin) {
            $admin->givePermissionTo($permission);
        }
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::where('name', self::PERMISSION)->where('guard_name', 'web')->delete();
    }
};
