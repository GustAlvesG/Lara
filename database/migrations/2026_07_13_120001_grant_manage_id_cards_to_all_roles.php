<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permission = Permission::firstOrCreate([
            'name'       => 'manage id cards',
            'guard_name' => 'web',
        ]);

        Role::all()->each(fn($role) => $role->givePermissionTo($permission));
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        if ($permission = Permission::where('name', 'manage id cards')->first()) {
            Role::all()->each(fn($role) => $role->revokePermissionTo($permission));
        }
    }
};
