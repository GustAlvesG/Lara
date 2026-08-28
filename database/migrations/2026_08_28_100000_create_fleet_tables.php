<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_vehicles', function (Blueprint $table) {
            $table->id();
            // O nome é como a portaria chama o carro ("Celta", "Caminhão") e é
            // por ele que o veículo é resolvido na API — daí ser único.
            $table->string('name')->unique();
            $table->string('plate')->nullable()->unique();
            $table->string('description')->nullable();
            // Espelho do último hodômetro registrado. A verdade continua sendo a
            // última viagem; esta coluna evita recalcular a cada consulta e é o
            // piso de validação da próxima saída.
            $table->unsignedInteger('current_odometer')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('created_by_user')->nullable();
            $table->unsignedBigInteger('updated_by_user')->nullable();
            $table->timestamps();

            $table->index('active');
        });

        Schema::create('fleet_trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fleet_vehicle_id')->constrained('fleet_vehicles')->cascadeOnUpdate()->restrictOnDelete();

            // O motorista é gravado sempre como texto: quem sai com o carro pode
            // não estar na tabela de funcionários (estagiário, terceiro, um nome
            // digitado na portaria). Quando bate com um employee, o vínculo é
            // guardado junto para dar relatório por matrícula.
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->string('driver_name');
            $table->string('driver_document')->nullable();

            $table->string('destination');

            $table->unsignedInteger('departure_odometer');
            $table->timestamp('departure_at');
            $table->string('departure_operator')->nullable();
            $table->unsignedBigInteger('departure_registered_by')->nullable();
            $table->text('departure_obs')->nullable();

            $table->unsignedInteger('return_odometer')->nullable();
            $table->timestamp('return_at')->nullable();
            $table->string('return_operator')->nullable();
            $table->unsignedBigInteger('return_registered_by')->nullable();
            $table->text('return_obs')->nullable();

            // Gravado no fechamento para que relatório de km rodado seja um
            // SUM, sem depender de subtração linha a linha.
            $table->unsignedInteger('distance_km')->nullable();

            $table->string('status')->default('open');
            $table->timestamp('canceled_at')->nullable();
            $table->string('cancel_reason')->nullable();

            // Marca a viagem cujos números não fecharam (hodômetro menor que o
            // anterior, salto acima do teto) e foi aceita mesmo assim. Serve de
            // filtro de conferência — o registro nunca é bloqueado em silêncio.
            $table->boolean('odometer_alert')->default(false);

            $table->timestamps();

            $table->index(['fleet_vehicle_id', 'status']);
            $table->index('departure_at');
            $table->index('employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_trips');
        Schema::dropIfExists('fleet_vehicles');
    }
};
