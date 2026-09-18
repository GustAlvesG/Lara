<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Configuração de vídeo do Replay: orientação e duração do clipe.
     *
     * Vale por ESPORTE (place_group_id) ou por QUADRA (place_id) — exatamente
     * um dos dois preenchido, nunca os dois. Quem resolve a herança
     * (quadra > esporte > padrão) é o ReplayResolver; aqui só guardamos as
     * linhas, com um índice único por dono para que não existam duas
     * configurações disputando a mesma quadra.
     *
     * `clip_seconds` são sempre os segundos ANTERIORES ao aperto do botão —
     * não há pós-roll. O teto de 60s é regra de negócio e mora na validação,
     * não no banco: um dia pode subir sem migration.
     */
    public function up(): void
    {
        Schema::create('replay_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('place_group_id')->nullable()->constrained('place_groups')->cascadeOnDelete();
            $table->foreignId('place_id')->nullable()->constrained('places')->cascadeOnDelete();
            $table->string('orientation', 12)->default('horizontal');
            $table->unsignedSmallInteger('clip_seconds')->default(30);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique('place_group_id');
            $table->unique('place_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('replay_settings');
    }
};
