<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Vínculo jogador ↔ time por temporada. Um jogador pode estar em mais de
     * um time da mesma equipe (ex.: sobe do Sub-17 para o adulto) com números
     * de camisa diferentes em cada um — por isso a unicidade é por
     * (time, jogador, temporada), não por jogador.
     */
    public function up(): void
    {
        Schema::create('elencos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('time_id')->constrained('times')->cascadeOnDelete();
            $table->foreignId('jogador_id')->constrained('jogadores')->cascadeOnDelete();
            $table->year('temporada');
            $table->string('numero')->nullable();
            $table->string('posicao')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique(['time_id', 'jogador_id', 'temporada']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('elencos');
    }
};
