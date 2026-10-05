<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Como o valor do contrato foi obtido.
     *
     *   hourly  o padrão: blocos de 15 minutos × preço da função
     *   fixed   valor fixo, digitado por quem registra o contrato
     *
     * O valor em si continua em `price` nos dois casos — é a coluna que lote,
     * financeiro, Pix e o corpo do contrato já leem. O que esta coluna guarda é
     * a origem dele: sem ela, um valor que não bate com horas × função não se
     * distinguiria de um erro de cálculo, e a edição de um contrato de valor
     * fixo o recalcularia pelas horas.
     *
     * Todo contrato anterior é `hourly`, que é o que o default grava.
     */
    public function up(): void
    {
        Schema::table('freelancer_services', function (Blueprint $table) {
            $table->string('pricing_mode', 10)->default('hourly')->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('freelancer_services', function (Blueprint $table) {
            $table->dropColumn('pricing_mode');
        });
    }
};
