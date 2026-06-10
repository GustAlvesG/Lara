<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permission = Permission::firstOrCreate(
            ['name' => 'manage home assistant', 'guard_name' => 'web'],
            ['description' => 'Permite gerenciar o painel Home Assistant']
        );

        $admin = Role::findByName('admin', 'web');
        if ($admin) {
            $admin->givePermissionTo($permission);
        }
    }

    public function down(): void
    {
        Permission::where('name', 'manage home assistant')->delete();
    }
};
