<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Parking;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        Parking::create([
            'plate' => 'NPS8047',
            'color' => 'preto',
            'entry_date' => '2024-05-10 15:15:30',
            'file' => 'NPS8047/2024-05-10-15-15.vehicleBody.jpg',
        ]);

        Parking::create([
            'plate' => 'NPS8047',
            'color' => 'preto',
            'entry_date' => '2024-05-10 15:15:30',
            'file' => 'NPS8047/2024-05-10-15-15.vehicleBody.jpg',
        ]);

        Parking::create([
            'plate' => 'LQC9689',
            'color' => 'azul',
            'entry_date' => '2024-05-10 15:40:30',
            'file' => 'LQC9689/2024-05-10-15-40.vehicleBody.jpg',
        ]);

        Parking::create([
            'plate' => 'LKV3216',
            'color' => 'preto',
            'entry_date' => '2024-05-10 14:36:30',
            'file' => 'LKV3216/2024-05-10-14-36.vehicleBody.jpg',
        ]);
    }
}
