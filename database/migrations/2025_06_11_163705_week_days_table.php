<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('weekdays', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('short_name');
            $table->string('name_pt');
            $table->string('short_name_pt');

        });

        // Insert the days of the week
        DB::table('weekdays')->insert([
            ['name' => 'sunday', 'short_name' => 'sun', 'name_pt' => 'domingo', 'short_name_pt' => 'dom'],
            ['name' => 'monday', 'short_name' => 'mon', 'name_pt' => 'segunda-feira', 'short_name_pt' => 'seg'],
            ['name' => 'tuesday', 'short_name' => 'tue', 'name_pt' => 'terça-feira', 'short_name_pt' => 'ter'],
            ['name' => 'wednesday', 'short_name' => 'wed', 'name_pt' => 'quarta-feira', 'short_name_pt' => 'qua'],
            ['name' => 'thursday', 'short_name' => 'thu', 'name_pt' => 'quinta-feira', 'short_name_pt' => 'qui'],
            ['name' => 'friday', 'short_name' => 'fri', 'name_pt' => 'sexta-feira', 'short_name_pt' => 'sex'],
            ['name' => 'saturday', 'short_name' => 'sat', 'name_pt' => 'sábado', 'short_name_pt' => 'sab'],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weekdays');
    }
};
