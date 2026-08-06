<?php

namespace Database\Seeders;

use App\Models\Placar\Modalidade;
use Illuminate\Database\Seeder;

class ModalidadeSeeder extends Seeder
{
    public function run(): void
    {
        $modalidades = [
            ['nome' => 'Futsal', 'slug' => Modalidade::FUTSAL],
            ['nome' => 'Basquete', 'slug' => Modalidade::BASQUETE],
            ['nome' => 'Vôlei', 'slug' => Modalidade::VOLEI],
        ];

        foreach ($modalidades as $modalidade) {
            Modalidade::firstOrCreate(['slug' => $modalidade['slug']], $modalidade);
        }
    }
}
