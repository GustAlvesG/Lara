<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Limpar o cache de permissões do Spatie (evita bugs de permissões fantasmas)
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 2. Criar Permissões Básicas
        $permissions_infoclube = [
            [ 'name' => 'create information', 'description' => 'Permite criar informações no InfoClube'],
            [ 'name' => 'edit information', 'description' => 'Permite editar informações no InfoClube'],
            [ 'name' => 'delete information', 'description' => 'Permite deletar informações no InfoClube'],
            [ 'name' => 'publish information', 'description' => 'Permite publicar informações no InfoClube'],
            [ 'name' => 'view information', 'description' => 'Permite visualizar informações no InfoClube'],
        ];

        $permissions_siv = [
            [ 'name' => 'search parking', 'description' => 'Permite buscar registro no SIV']
        ];

        $permission_smart_panel = [
            [ 'name' => 'manage smart panel', 'description' => 'Permite gerenciar o Smart Panel'],
        ];

        $permission_reserve = [
            [ 'name' => 'create reservations', 'description' => 'Permite criar reservas'],
            [ 'name' => 'view reservations', 'description' => 'Permite visualizar reservas'],
            [ 'name' => 'edit reservations', 'description' => 'Permite editar reservas'],
            [ 'name' => 'delete reservations', 'description' => 'Permite deletar reservas'],
            [ 'name' => 'manage reservations-configs', 'description' => 'Permite gerenciar configurações de reservas'],
        ];

        $permission_admin = [
            [ 'name' => 'manage users', 'description' => 'Permite gerenciar usuários'],
            [ 'name' => 'manage roles', 'description' => 'Permite gerenciar funções'],
            [ 'name' => 'manage permissions', 'description' => 'Permite gerenciar permissões'],
        ];

        $allPermissions = array_merge(
            $permissions_infoclube,
            $permissions_siv,
            $permission_smart_panel,
            $permission_reserve,
            $permission_admin
        );

        foreach ($allPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission['name']], ['description' => $permission['description']]);
        }

        // 3. Criar Roles e atribuir Permissões
        $role_admin = Role::firstOrCreate(['name' => 'admin']);
        $role_admin->givePermissionTo(Permission::all());

        $role_secretaria = Role::firstOrCreate(['name' => 'secretaria']);
        //Get all "name" from array of permissions
        $permissions_secretaria = array_merge($permissions_infoclube, $permissions_siv, $permission_reserve);
        foreach ( $permissions_secretaria as $key => $value ) {
            $permissions_secretaria[$key] = $value['name'];
        }
        $role_secretaria->givePermissionTo($permissions_secretaria);

        $role_comercial = Role::firstOrCreate(['name' => 'comercial']);
        $role_comercial->givePermissionTo('create reservations',
            'view reservations',
            'edit reservations',
            'delete reservations',
            'view information'
        );

        $role_default = Role::firstOrCreate(['name' => 'user']);
        $role_default->givePermissionTo('view information');

        $user = User::find(1);
        if ($user) {
            $user->assignRole('admin');
        }

        $user_test = User::find(71);
        if ($user_test) {
            $user_test->assignRole('user');
        }
        $this->command->info('Seed concluído: Roles, Permissions e Usuário Admin criados!');
    }
}
