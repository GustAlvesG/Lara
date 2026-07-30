<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_access_logs', function (Blueprint $table) {
            // Vincula o registro de acesso ao pedido de Uber que o originou e
            // anexa a imagem da solicitação ao próprio log, para que possa ser
            // consultada depois mesmo que o pedido seja removido futuramente.
            $table->unsignedBigInteger('uber_access_request_id')->nullable()->after('app_driver_id');
            $table->foreign('uber_access_request_id')->references('id')->on('uber_access_requests')->onDelete('set null');
            $table->string('screenshot_url', 2048)->nullable()->after('obs');
        });
    }

    public function down(): void
    {
        Schema::table('company_access_logs', function (Blueprint $table) {
            $table->dropForeign(['uber_access_request_id']);
            $table->dropColumn(['uber_access_request_id', 'screenshot_url']);
        });
    }
};
