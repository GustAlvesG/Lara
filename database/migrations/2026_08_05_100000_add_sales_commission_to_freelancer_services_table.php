<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aditivo de comissão de venda.
     *
     * Passa a haver DOIS tipos de aditivo, de naturezas opostas, e por isso a
     * coluna `amendment_type`:
     *
     *  - `schedule`   — muda horário/local e SUBSTITUI o contrato base no
     *                   pagamento (marca `amended_at` nele);
     *  - `commission` — remuneração variável sobre as vendas do turno, que
     *                   ACRESCE ao contrato base. Não marca `amended_at`: o
     *                   turno continua sendo pago pelo contrato, e a comissão é
     *                   paga por cima.
     *
     * Sem essa distinção, a comissão faria o clube deixar de pagar o turno.
     *
     * A comissão é exclusiva de quem tem `allows_sales_commission` na função —
     * hoje, só o Garçom. A permissão é uma coluna, e não o nome "Garçom" no
     * código, porque os nomes cadastrados mudam (e estão sujos: há função com
     * quebra de linha no nome e duas chamadas "TI").
     */
    public function up(): void
    {
        Schema::table('freelancer_services', function (Blueprint $table) {
            $table->string('amendment_type', 20)->nullable()->after('parent_service_id');
            $table->decimal('sales_amount', 12, 2)->nullable()->after('amendment_type');
            $table->string('commission_method', 10)->nullable()->after('sales_amount');
            // De onde veio o valor de venda: digitado no tablet (`manual`) ou,
            // no futuro, trazido do sistema de vendas.
            $table->string('sales_source', 20)->nullable()->after('commission_method');
        });

        Schema::table('function_freelancers', function (Blueprint $table) {
            $table->boolean('allows_sales_commission')->default(false)->after('price');
        });

        // Os aditivos que já existem são todos de horário/local.
        DB::table('freelancer_services')
            ->whereNotNull('parent_service_id')
            ->update(['amendment_type' => 'schedule']);

        // Correção de dados de uma vez só: daqui em diante quem manda é a
        // coluna. TRIM porque há nomes gravados com quebra de linha.
        DB::table('function_freelancers')
            ->whereRaw('LOWER(TRIM(name)) = ?', ['garçom'])
            ->update(['allows_sales_commission' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('freelancer_services', function (Blueprint $table) {
            $table->dropColumn(['amendment_type', 'sales_amount', 'commission_method', 'sales_source']);
        });

        Schema::table('function_freelancers', function (Blueprint $table) {
            $table->dropColumn('allows_sales_commission');
        });
    }
};
