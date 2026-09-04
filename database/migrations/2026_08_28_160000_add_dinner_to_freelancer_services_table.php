<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jantar do turno noturno.
     *
     * Quem cumpre 6 horas ou mais e está em serviço durante TODA a janela do
     * jantar (17:30 às 18:30) tem direito à refeição. O direito é da regra; o
     * que estas colunas guardam é a RESPOSTA do freelancer à pergunta feita no
     * tablet logo depois da assinatura — é ela que a cozinha usa para dimensionar
     * quantos pratos preparar, e por isso precisa estar registrada, e não
     * deduzida do contrato na hora do almoço.
     *
     * - `dinner_wanted` — null enquanto não respondeu (inclusive nos contratos
     *   que nem chegam a ser perguntados). true/false depois de respondida.
     * - `dinner_date` — o DIA do jantar a que a resposta se refere. Não é
     *   sempre `start_date`: um turno que vira a meia-noite pode cobrir a janela
     *   do jantar do dia seguinte, e é por este campo que a cozinha consulta.
     * - `dinner_answered_at` / `dinner_answered_by` — quando e sob condução de
     *   qual operador do tablet a resposta foi registrada.
     */
    public function up(): void
    {
        Schema::table('freelancer_services', function (Blueprint $table) {
            $table->boolean('dinner_wanted')->nullable()->after('pix_key_confirmed_at');
            $table->date('dinner_date')->nullable()->after('dinner_wanted');
            $table->dateTime('dinner_answered_at')->nullable()->after('dinner_date');
            // Sem foreign key para `users` de propósito: o model User fixa a
            // conexão `mysql`, e a FK amarraria esta tabela a ela.
            $table->unsignedBigInteger('dinner_answered_by')->nullable()->after('dinner_answered_at');

            // A consulta da cozinha é exatamente este par: "quem disse SIM no
            // jantar do dia X". Um índice composto atende a pergunta inteira.
            $table->index(['dinner_date', 'dinner_wanted'], 'freelancer_services_dinner_idx');
        });
    }

    public function down(): void
    {
        Schema::table('freelancer_services', function (Blueprint $table) {
            $table->dropIndex('freelancer_services_dinner_idx');
            $table->dropColumn(['dinner_wanted', 'dinner_date', 'dinner_answered_at', 'dinner_answered_by']);
        });
    }
};
