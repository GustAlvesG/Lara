<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Contrato aditivo.
     *
     * Um turno pode mudar depois de o contrato já estar assinado — o serviço é
     * antecipado, esticado, encurtado ou muda de local. Contrato assinado não se
     * altera; o que se faz é um ADITIVO: um novo contrato que referencia o
     * contrato base (`parent_service_id`) e repete tudo dele, menos horário de
     * início, horário de término e local.
     *
     * O contrato base NÃO morre: ele continua sendo assinado pelas duas partes e
     * fica no histórico como o documento que as partes firmaram. O que o par
     * `amended_at` / `amendment_service_id` faz é tirá-lo do caminho do
     * DINHEIRO — ele não entra em lote nem no financeiro, porque quem paga o
     * turno é o aditivo, com o período já corrigido. Sem isso, o mesmo turno
     * seria pago duas vezes.
     */
    public function up(): void
    {
        Schema::table('freelancer_services', function (Blueprint $table) {
            $table->foreignId('parent_service_id')->nullable()->after('id')
                ->constrained('freelancer_services')->nullOnDelete();

            $table->timestamp('amended_at')->nullable()->after('parent_service_id');
            $table->foreignId('amendment_service_id')->nullable()->after('amended_at')
                ->constrained('freelancer_services')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('freelancer_services', function (Blueprint $table) {
            $table->dropConstrainedForeignId('amendment_service_id');
            $table->dropColumn('amended_at');
            $table->dropConstrainedForeignId('parent_service_id');
        });
    }
};
