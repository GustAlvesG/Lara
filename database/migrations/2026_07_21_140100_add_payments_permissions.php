<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    private array $permissions = [
        'view payments',
        'manage payments',
    ];

    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $created = collect($this->permissions)->map(
            fn (string $name) => Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'])
        );

        // Mesmos papéis que hoje lidam com reservas ganham acesso a pagamentos.
        $admin = Role::where('name', 'admin')->first();
        $admin?->givePermissionTo($created);

        $secretaria = Role::where('name', 'secretaria')->first();
        $secretaria?->givePermissionTo($created);

        $comercial = Role::where('name', 'comercial')->first();
        $comercial?->givePermissionTo(Permission::where('name', 'view payments')->first());
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = Permission::whereIn('name', $this->permissions)->get();
        Role::all()->each(fn ($role) => $role->revokePermissionTo($permissions));
        Permission::whereIn('name', $this->permissions)->delete();
    }
};
