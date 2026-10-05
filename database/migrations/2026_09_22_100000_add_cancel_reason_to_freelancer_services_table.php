<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Motivo da baixa do contrato: cancelamento comum ou FALTA do freelancer.
     *
     * A falta não é um estado novo — é um cancelamento com outro motivo. Isso é
     * de propósito: tudo o que já exclui o contrato cancelado (a contagem do
     * limite semanal, o lote, o financeiro, a liberação na portaria) passa a
     * excluir a falta sem que nenhuma dessas regras precise ser tocada. O que a
     * coluna acrescenta é a leitura: "não trabalhou" e "cancelamos" são coisas
     * diferentes para quem escala e para quem cobra.
     *
     * Contratos cancelados antes desta coluna ficam com o motivo nulo, e são
     * lidos como cancelamento comum — que é o que eram.
     */
    public function up(): void
    {
        Schema::table('freelancer_services', function (Blueprint $table) {
            $table->string('cancel_reason', 20)->nullable()->after('cancelled_by');
        });
    }

    public function down(): void
    {
        Schema::table('freelancer_services', function (Blueprint $table) {
            $table->dropColumn('cancel_reason');
        });
    }
};
