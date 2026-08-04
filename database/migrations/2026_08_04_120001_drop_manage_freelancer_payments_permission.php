<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Aposenta a permissão `manage freelancer payments`.
 *
 * O acesso ao financeiro dos freelancers virou vínculo de setor (Contabilidade
 * ou Gerência, qualquer papel), avaliado pelo Gate `manage-freelancer-payments`
 * — ver AppServiceProvider e User::canManageFreelancerPayments(). Deixar a
 * permissão no banco só criaria uma linha na tela de permissões que, marcada,
 * não daria acesso a nada.
 *
 * O rollback recria a permissão e devolve à role `admin`, que era quem a tinha.
 * Concessões diretas a usuários, se houver, não são restauradas — o `down()`
 * não tem como saber quem eram depois de apagadas.
 */
return new class extends Migration
{
    private const NAME = 'manage freelancer payments';

    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::where('name', self::NAME)->where('guard_name', 'web')->delete();
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permission = Permission::firstOrCreate(
            ['name' => self::NAME, 'guard_name' => 'web'],
            ['description' => 'Permite acessar o financeiro dos freelancers e dar baixa de pagamento'],
        );

        Role::where('name', 'admin')->first()?->givePermissionTo($permission);
    }
};
