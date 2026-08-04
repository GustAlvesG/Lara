<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_access_logs', function (Blueprint $table) {
            // O acesso do freelancer entra no MESMO histórico dos terceirizados:
            // é a mesma portaria e a mesma consulta por CPF. O freelancer fica
            // gravado mesmo quando o acesso é negado (existe, mas não tem
            // serviço na janela); o contrato só existe quando foi ele que
            // liberou a entrada.
            $table->unsignedBigInteger('freelancer_id')->nullable()->after('uber_access_request_id');
            $table->foreign('freelancer_id')->references('id')->on('freelancers')->onDelete('set null');

            $table->unsignedBigInteger('freelancer_service_id')->nullable()->after('freelancer_id');
            $table->foreign('freelancer_service_id')->references('id')->on('freelancer_services')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('company_access_logs', function (Blueprint $table) {
            $table->dropForeign(['freelancer_id']);
            $table->dropForeign(['freelancer_service_id']);
            $table->dropColumn(['freelancer_id', 'freelancer_service_id']);
        });
    }
};
