<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FunctionFreelancerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $functions = [
            ['name' => 'TI', 'description' => 'Tecnologia da Informação', 'price' => '30.00'],
            ['name' => 'Atendente', 'description' => 'Atendimento ao Cliente', 'price' => '20.00'],
            ['name' => 'Garçom', 'description' => 'Serviço de mesa', 'price' => '10.00'],
        ];

        DB::table('function_freelancers')->insert($functions);
    }
}
