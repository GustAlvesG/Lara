<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bot de atendimento do WhatsApp rodando na Lara, no lugar do bot da Poli.
 *
 * Três tabelas, cada uma com um dono só:
 *   - bot_flows     o que o bot pergunta (definição editável);
 *   - bot_sessions  onde cada contato está na conversa;
 *   - poli_messages o que o bot recebeu e mandou, com o texto mascarado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_flows', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->boolean('active')->default(true);

            /*
             * O fluxo inteiro num JSON só (passos, validações, opções, ações),
             * validado por App\Services\PoliBot\FlowDefinition. Uma tabela por
             * passo não compraria nada: o fluxo é sempre lido e gravado inteiro.
             */
            $table->json('definition');
            $table->timestamps();
        });

        Schema::create('bot_sessions', function (Blueprint $table) {
            $table->string('contact_uuid')->primary();

            // idle: sem conversa em curso · flow: dentro de um fluxo ·
            // human: um atendente assumiu, e o bot fica quieto.
            $table->string('state')->default('idle');
            $table->string('flow_slug')->nullable();
            $table->string('step_key')->nullable();

            // Respostas já coletadas no fluxo, por nome de variável.
            $table->json('data')->nullable();
            $table->unsignedSmallInteger('tentativas')->default(0);

            // A pergunta em aberto: é contra ela que se confere um toque em
            // menu antigo.
            $table->string('prompt_message_uuid')->nullable();

            $table->string('attendance_uuid')->nullable()->index();
            $table->string('contact_phone')->nullable();
            $table->string('contact_name')->nullable();
            $table->timestamp('human_since')->nullable();
            $table->timestamp('last_interaction_at')->nullable();
            $table->timestamps();
        });

        Schema::create('poli_messages', function (Blueprint $table) {
            $table->id();

            // uuid da Poli (entrada: o da mensagem; saída: o devolvido no
            // POST). Envio em modo sombra ganha um uuid local "shadow-…".
            $table->string('uuid')->unique();
            $table->string('contact_uuid')->index();
            $table->string('direction', 8);           // IN / OUT
            $table->string('type', 16);               // TEXT, TEMPLATE, IMAGE, MEDIA, ACTION
            $table->text('texto')->nullable();         // mascarado — ver PoliTextMask
            $table->string('template_uuid')->nullable();

            // Opções que um menu da Lara ofereceu. O toque volta citando o
            // uuid desta linha, mas SEM as opções (o contexto chega "[]").
            $table->json('options')->nullable();

            $table->string('flow_slug')->nullable();
            $table->string('step_key')->nullable();
            $table->string('ack')->nullable();
            $table->string('error', 500)->nullable();

            // Não saiu de verdade: modo sombra, ou ação simulada.
            $table->boolean('shadow')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poli_messages');
        Schema::dropIfExists('bot_sessions');
        Schema::dropIfExists('bot_flows');
    }
};
