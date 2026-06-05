<?php

namespace Database\Seeders;

use App\Models\Sector;
use Illuminate\Database\Seeder;

class SectorSeeder extends Seeder
{
    public function run(): void
    {
        $sectors = [
            'Manutenção',
            'Comercial',
            'Atendimento',
            'Finanças',
            'Portaria',
            'Esporte',
            'Principal',
            'RH',
            'Almoxarifado',
            'Social',
            'TI',
            'Marketing',
        ];

        foreach ($sectors as $name) {
            Sector::firstOrCreate(['name' => $name]);
        }
    }
}
