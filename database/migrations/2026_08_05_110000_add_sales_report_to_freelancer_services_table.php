<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Relatório de vendas da comissão.
     *
     * A comissão deixa de ser um número digitado: ela é apurada no MultiVendas
     * (conexão `mv_sqlsrv`) pelo login do vendedor e pelo período do turno. O
     * relatório inteiro é GRAVADO junto com o documento, e não apenas
     * consultado, por duas razões:
     *
     *  - ele é anexado ao termo que as partes assinam, e um anexo que muda
     *    depois da assinatura não prova nada;
     *  - o MultiVendas continua vivo — vendas podem ser canceladas ou
     *    corrigidas —, e o documento tem de continuar mostrando o que foi
     *    apurado no dia.
     *
     * `sales_login` e o período ficam gravados porque são os parâmetros da
     * apuração: sem eles não dá para refazer a conta nem conferir o anexo.
     */
    public function up(): void
    {
        Schema::table('freelancer_services', function (Blueprint $table) {
            $table->string('sales_login', 100)->nullable()->after('sales_source');
            $table->dateTime('sales_period_start')->nullable()->after('sales_login');
            $table->dateTime('sales_period_end')->nullable()->after('sales_period_start');
            $table->json('sales_report')->nullable()->after('sales_period_end');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('freelancer_services', function (Blueprint $table) {
            $table->dropColumn(['sales_login', 'sales_period_start', 'sales_period_end', 'sales_report']);
        });
    }
};
