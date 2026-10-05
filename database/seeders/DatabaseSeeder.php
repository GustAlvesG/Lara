<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // Seed padrão: o catálogo de permissões no banco (o mesmo que o deploy roda).
        $this->call(PermissionCatalogSeeder::class);
        $this->call(SectorAccessSeeder::class);
        // Dado de referência fixo do Placar Clube (3 modalidades), não demo.
        $this->call(ModalidadeSeeder::class);
    }
}
