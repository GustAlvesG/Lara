<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trilha do mapa: quem criou, quem importou, quem mexeu em qual preço, quem
 * escolheu o vencedor e quem exportou.
 *
 * Um mapa de cotação decide para onde vai dinheiro, e a pergunta que aparece
 * depois é sempre a mesma: "quem mudou este preço, e quando?". A planilha em
 * Excel não responde isso — é a razão principal de o módulo existir.
 *
 * `payload` guarda o antes/depois em JSON. Sem esquema fixo de propósito: cada
 * ação registra o que é relevante para ela, e o formato de uma ação nova não
 * pode exigir migração.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cotacao_mapa_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cotacao_mapa_id')
                ->constrained('cotacao_mapas')
                ->cascadeOnDelete();

            // Sem FK para `users`: o model User fixa a conexão `mysql`. O nome
            // vai junto como retrato — usuário renomeado ou removido não apaga
            // a resposta para "quem fez isso".
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_nome', 100)->nullable();

            $table->string('acao', 40);
            $table->json('payload')->nullable();

            // Só created_at: um log que pode ser atualizado não é um log.
            $table->timestamp('created_at')->nullable()->index();

            $table->index(['cotacao_mapa_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotacao_mapa_logs');
    }
};
