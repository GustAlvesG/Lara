<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Log append-only — o coração do scout. Nunca é alvo de UPDATE/DELETE pela
     * API; correção se faz com um evento novo do tipo `estorno` referenciando
     * o uuid original no payload. Ver docs/placar-clube-api.md.
     */
    public function up(): void
    {
        Schema::create('jogo_eventos', function (Blueprint $table) {
            $table->id();
            // Gerado pelo Node — chave de idempotência contra reenvio da fila offline.
            $table->uuid('uuid')->unique();
            $table->foreignId('jogo_id')->constrained('jogos')->cascadeOnDelete();
            // Contador incremental do jogo, gerado pelo Node. Garante ordem
            // mesmo com relógios divergentes entre dispositivos.
            $table->unsignedInteger('sequencia');
            $table->string('tipo');
            // Nulos: o log sobrevive mesmo que o time/jogador seja apagado depois.
            $table->foreignId('time_id')->nullable()->constrained('times')->nullOnDelete();
            $table->foreignId('jogador_id')->nullable()->constrained('jogadores')->nullOnDelete();
            $table->integer('valor')->nullable();
            $table->integer('periodo')->nullable();
            // Tempo do cronômetro no momento do evento — não o relógio de parede.
            $table->unsignedBigInteger('cronometro_ms')->nullable();
            $table->timestamp('ocorrido_em', 3);
            $table->json('payload')->nullable();
            $table->timestamps();

            // Cobre também as buscas por jogo_id isolado — não precisa de um
            // índice adicional só em (jogo_id, sequencia).
            $table->unique(['jogo_id', 'sequencia']);
            $table->index('jogador_id');
            $table->index('tipo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jogo_eventos');
    }
};
