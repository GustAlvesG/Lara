<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A ponte com o mundo físico: cada câmera/botão instalado numa quadra.
     *
     * `external_id` é o identificador que o SISTEMA DE CAPTURA usa para se
     * referir ao equipamento. Toda a API do Replay é endereçada por ele, e
     * não pelo id do Lara — assim o outro sistema não precisa conhecer nossa
     * numeração interna, e trocar o equipamento de quadra é editar uma linha
     * aqui, sem mexer em nada do lado de lá.
     *
     * `position` existe porque campo de futebol tem duas câmeras (uma por
     * metade); é rótulo para gente ler ("Lado A"), não chave de nada. O teto
     * de duas câmeras por quadra é regra de negócio e mora na validação.
     */
    public function up(): void
    {
        Schema::create('replay_cameras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('place_id')->constrained('places')->cascadeOnDelete();
            $table->string('external_id')->unique();
            $table->string('name');
            $table->string('position')->nullable();
            $table->boolean('active')->default(true);
            // Alimentado pelo heartbeat: é o que denuncia câmera muda antes de
            // alguém reclamar que o botão não grava.
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index('place_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('replay_cameras');
    }
};
