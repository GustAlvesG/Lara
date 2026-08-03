<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Histórico do chat com a Lara.
 *
 * A IA guarda o contexto da conversa só em memória, indexado pelo usuário: se
 * o processo dela reinicia, o histórico some. Guardar do nosso lado serve a
 * outras duas coisas que a memória dela não cobre — o funcionário reabrir a
 * página e ainda ver a conversa, e termos como auditar o que o agente andou
 * respondendo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lara_messages', function (Blueprint $table) {
            $table->id();

            // Sem foreign key para `users` de propósito: o model User fixa a
            // conexão `mysql`, enquanto esta tabela segue a conexão padrão.
            // Quando as duas divergem, a FK não é garantida.
            $table->unsignedBigInteger('user_id');

            $table->uuid('conversation_uuid');
            $table->string('role', 16);            // user | assistant
            $table->text('conteudo');
            $table->string('status', 16)->default('ok');  // ok | erro | desativado
            $table->unsignedInteger('latencia_ms')->nullable();
            $table->string('erro')->nullable();
            $table->timestamp('created_at')->nullable();

            // A tela sempre lê "as mensagens desta conversa deste usuário, em
            // ordem" — o índice composto cobre exatamente esse acesso.
            $table->index(['user_id', 'conversation_uuid', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lara_messages');
    }
};
