<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Cria a permissão `authorize purchase orders`.
 *
 * Ela já existe no `RolesAndPermissionsSeeder`, mas o seeder **não roda no
 * deploy** — e rodá-lo em produção faria mais coisa do que criar uma permissão
 * (recria papéis e atribui roles a usuários específicos por id). Sem esta
 * migration, subir o módulo significaria 403 para todo mundo até alguém lembrar
 * de criar a linha na mão, e o sintoma — "a permissão não aparece na tela de
 * papéis" — não aponta para a causa.
 *
 * Concede à role `admin` porque é o que o seeder faz (`givePermissionTo(all)`).
 * Quem mais recebe é decisão de operação, na tela de Permissões.
 */
return new class extends Migration
{
    private const NAME = 'authorize purchase orders';

    public function up(): void
    {
        // Sem isto, a permissão recém-criada não aparece para o Spatie até o
        // cache dele expirar — inclusive para o `givePermissionTo` abaixo.
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permission = Permission::firstOrCreate(
            ['name' => self::NAME, 'guard_name' => 'web'],
            ['description' => 'Permite ver e autorizar ordens de compra do Questor'],
        );

        Role::where('name', 'admin')->first()?->givePermissionTo($permission);
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::where('name', self::NAME)->where('guard_name', 'web')->delete();
    }
};
