<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Role;

class SetUserAsRoleUser extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $userRole = Role::where('name', 'secretaria')->first();

        $users = User::all();

        foreach ($users as $user) {
            if (!$user->hasRole('secretaria')) {
                $user->assignRole($userRole);
            }
        }

        $user = User::find(1);
        if ($user) {
            $user->assignRole('admin');
        }
    }
}
