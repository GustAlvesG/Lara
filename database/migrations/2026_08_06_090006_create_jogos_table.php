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
        Schema::create('jogos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competicao_id')->nullable()->constrained('competicoes')->nullOnDelete();
            $table->foreignId('modalidade_id')->constrained('modalidades')->restrictOnDelete();
            // Duas FKs para a mesma tabela `times` — nomeadas explicitamente
            // porque o nome da coluna não deixa o Laravel adivinhar a tabela.
            $table->foreignId('time_casa_id')->constrained('times')->restrictOnDelete();
            $table->foreignId('time_fora_id')->constrained('times')->restrictOnDelete();
            $table->dateTime('data_hora');
            $table->string('local')->nullable();
            $table->enum('status', ['agendado', 'ao_vivo', 'encerrado', 'cancelado'])->default('agendado');
            // Cache derivado de `jogo_eventos` — a verdade é sempre o log.
            $table->integer('placar_casa')->nullable();
            $table->integer('placar_fora')->nullable();
            $table->integer('sets_casa')->nullable();
            $table->integer('sets_fora')->nullable();
            $table->integer('periodos_jogados')->nullable();
            $table->boolean('criado_em_campo')->default(false);
            // Guarda a divergência entre o placar enviado ao encerrar e o
            // recalculado a partir dos eventos (ver POST /jogos/{id}/encerrar).
            $table->text('observacoes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jogos');
    }
};
