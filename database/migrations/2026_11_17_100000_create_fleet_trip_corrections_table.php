<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Correção de km de viagem (SIV › Viagens, 17/11/2026). A viagem guarda só o
 * número certo; o que estava antes, quem trocou e por quê ficam aqui — sem
 * isso, corrigir km seria apagar a única prova do que a portaria registrou.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fleet_trip_corrections')) {
            return;
        }

        Schema::create('fleet_trip_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fleet_trip_id')
                ->constrained('fleet_trips', indexName: 'ftc_trip_fk')
                ->cascadeOnDelete();

            $table->unsignedInteger('old_departure_odometer');
            $table->unsignedInteger('new_departure_odometer');
            $table->unsignedInteger('old_return_odometer')->nullable();
            $table->unsignedInteger('new_return_odometer')->nullable();
            $table->unsignedInteger('old_distance_km')->nullable();
            $table->unsignedInteger('new_distance_km')->nullable();

            $table->string('reason', 500);
            // Nome gravado junto: o histórico tem de continuar legível mesmo
            // que o usuário seja desativado depois.
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name')->nullable();

            $table->timestamps();

            $table->index(['fleet_trip_id', 'created_at'], 'ftc_trip_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_trip_corrections');
    }
};
