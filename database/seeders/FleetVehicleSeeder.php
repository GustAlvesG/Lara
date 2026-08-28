<?php

namespace Database\Seeders;

use App\Models\Fleet\FleetVehicle;
use Illuminate\Database\Seeder;

/**
 * A frota de hoje. É `firstOrCreate` de propósito: rodar o seeder de novo não
 * pode zerar a quilometragem de um veículo que já está em uso.
 *
 * Placa e quilometragem inicial ficam em branco — são preenchidas na tela de
 * veículos, com o número lido no painel de cada carro.
 */
class FleetVehicleSeeder extends Seeder
{
    public function run(): void
    {
        $vehicles = [
            ['name' => 'Celta',    'description' => 'Carro de serviço'],
            ['name' => 'Caminhão', 'description' => 'Cargas e mudanças'],
            ['name' => 'Polo',     'description' => 'Carro de serviço'],
        ];

        foreach ($vehicles as $vehicle) {
            FleetVehicle::firstOrCreate(
                ['name' => $vehicle['name']],
                ['description' => $vehicle['description'], 'active' => true]
            );
        }

        $this->command->info('Frota: Celta, Caminhão e Polo disponíveis.');
    }
}
