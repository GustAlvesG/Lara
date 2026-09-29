<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cada gravação de um fluxo do bot, pela tela de edição.
 *
 * O fluxo em vigor é sempre o de bot_flows; aqui fica o caminho até ele —
 * quem mudou, quando, e o JSON inteiro de cada versão, para desfazer uma
 * edição que deu errado sem depender de backup.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_flow_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_flow_id')->constrained('bot_flows')->cascadeOnDelete();
            $table->string('name');
            $table->boolean('active');
            $table->json('definition');

            // Sem chave estrangeira: users vive na conexão mysql, e o nome
            // fica gravado para o histórico sobreviver a usuário removido.
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['bot_flow_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_flow_versions');
    }
};
