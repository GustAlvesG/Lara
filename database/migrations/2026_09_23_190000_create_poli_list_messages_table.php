<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índice das listas (menus de opções) que a Poli ENVIA.
 *
 * Existe para responder uma pergunta só: "este toque veio de um menu nosso, e
 * do menu desta conversa?". Sem isso não há como distinguir o associado que
 * tocou no botão daquele que digitou "Carro de Aplicativo" na mão, nem de quem
 * rolou a conversa e tocou no menu de dias atrás — e os dois casos entram no
 * fluxo com o passo trocado, gravando a matrícula no lugar do nome.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('poli_list_messages', function (Blueprint $table) {
            $table->id();

            /*
             * A chave é o `value.uuid` do evento de saída (`event: "sent"`),
             * porque é exatamente ele que o toque devolve em
             * `value.context.message.uuid`.
             *
             * O wamid de `metadata.external_message_id` NÃO serve: ele também
             * vem no evento de saída, mas o contexto da resposta não o cita, e
             * indexar por ele não junta coisa nenhuma.
             */
            $table->string('poli_message_uuid')->unique();

            /*
             * O atendimento a que o menu pertence. É o coração da trava: o
             * menu de outro dia é de um atendimento que já foi encerrado
             * (`closed_reason: FINISHED_BY_SYSTEM` ao fim do fluxo), enquanto
             * o toque nele abre um atendimento novo. Comparar os dois dispensa
             * janela de tempo, "mesmo dia" e qualquer leitura de relógio.
             */
            $table->string('attendance_uuid')->nullable()->index();

            $table->string('contact_uuid')->nullable()->index();

            // Só diagnóstico: identidade gerada pela Poli, que muda se alguém
            // recriar o menu. Nenhuma regra depende dela.
            $table->string('template_name')->nullable();

            // As linhas do menu (title/description), para saber QUAL opção foi
            // tocada — a resposta chega como "título\ndescrição" e é só isso
            // que identifica a escolha.
            $table->json('rows');

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poli_list_messages');
    }
};
