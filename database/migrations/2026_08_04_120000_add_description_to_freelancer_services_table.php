<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Campo livre de esclarecimento do serviço. É apenas informativo: não
     * entra em cálculo, contrato ou assinatura. Existe porque hoje o pessoal
     * usa o campo "Evento/Local" para justificar o serviço, o que polui um
     * dado que deveria conter só o evento.
     */
    public function up(): void
    {
        Schema::table('freelancer_services', function (Blueprint $table) {
            $table->text('description')->nullable()->after('location');
        });
    }

    public function down(): void
    {
        Schema::table('freelancer_services', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
