<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ordem de processamento das mensagens que chegam da Poli.
 *
 * O webhook entrega fora de ordem: em 7 dias de produção, 8 mensagens em 2081
 * chegaram depois de outra que a Poli tinha enviado DEPOIS delas — uma com 12
 * segundos de defasagem. Como o fluxo é uma máquina de estados, uma inversão
 * joga a resposta no campo errado e desloca o pedido inteiro.
 *
 * Estas três colunas são o que permite processar na ordem certa em vez da
 * ordem de chegada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('uber_access_request_messages', function (Blueprint $table) {
            /*
             * `value.metadata.deprecated_message_id`: o contador sequencial da
             * Poli. É a chave de ordem, e não o `value.timestamp` — que tem
             * resolução de segundos e por isso não enxerga 4 das 12 inversões
             * medidas; num caso ele chega a mentir, mostrando como mais antiga
             * uma mensagem que a sequência prova ser posterior.
             *
             * O nome "deprecated" é da Poli, então o parser cai no timestamp
             * se ele sumir. Hoje está presente em 2081 de 2081 mensagens.
             */
            $table->unsignedBigInteger('poli_sequence')->nullable()->after('poli_message_id');

            // Só existia dentro do JSON, e agora é consultado a cada mensagem
            // para achar as irmãs pendentes do mesmo contato.
            $table->string('contact_uuid')->nullable()->after('poli_sequence');

            // Nulo = ainda na fila. É o que distingue "já passou pelo fluxo" de
            // "vai passar", e sem isso não há como saber por quem esperar.
            $table->timestamp('processed_at')->nullable()->after('raw_payload');

            $table->index(['contact_uuid', 'poli_sequence'], 'uarm_contato_sequencia_idx');
            $table->index(['processed_at'], 'uarm_processed_idx');
        });

        /*
         * Linhas antigas ficam com as três colunas nulas, e isso é seguro: a
         * busca por antecessora compara `poli_sequence <`, e NULL nunca casa
         * com essa comparação. Elas não seguram ninguém. Fica sem UPDATE de
         * backfill de propósito — não vale travar a tabela em produção por um
         * dado que ninguém vai ler.
         */
    }

    public function down(): void
    {
        Schema::table('uber_access_request_messages', function (Blueprint $table) {
            $table->dropIndex('uarm_contato_sequencia_idx');
            $table->dropIndex('uarm_processed_idx');
            $table->dropColumn(['poli_sequence', 'contact_uuid', 'processed_at']);
        });
    }
};
