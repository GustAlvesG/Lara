<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Quem foi relacionado NESTE jogo — distinto do elenco da temporada, que é
     * só o ponto de partida quando ainda não existe escalação própria.
     */
    public function up(): void
    {
        Schema::create('escalacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jogo_id')->constrained('jogos')->cascadeOnDelete();
            $table->foreignId('time_id')->constrained('times')->cascadeOnDelete();
            $table->foreignId('jogador_id')->constrained('jogadores')->cascadeOnDelete();
            $table->string('numero');
            $table->boolean('titular')->default(false);
            $table->boolean('capitao')->default(false);
            $table->timestamps();

            $table->unique(['jogo_id', 'jogador_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('escalacoes');
    }
};
