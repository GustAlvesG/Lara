<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Permissão exclusiva da aba Financeiro. Diferente de "manage freelancers"
     * (concedida a todos os papéis), esta nasce apenas no admin — quem mais
     * precisar dar baixa recebe pela tela de permissões.
     */
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permission = Permission::firstOrCreate(
            ['name' => 'manage freelancer payments', 'guard_name' => 'web'],
            ['description' => 'Permite acessar o financeiro dos freelancers e dar baixa de pagamento'],
        );

        Role::where('name', 'admin')->first()?->givePermissionTo($permission);
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::where('name', 'manage freelancer payments')->delete();
    }
};
