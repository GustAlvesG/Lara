<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_access_logs', function (Blueprint $table) {
            // A liberação pontual entra no MESMO histórico dos terceirizados e
            // freelancers: é a mesma portaria e a mesma consulta por CPF.
            $table->unsignedBigInteger('one_off_access_id')->nullable()->after('freelancer_service_id');
            $table->foreign('one_off_access_id')->references('id')->on('one_off_accesses')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('company_access_logs', function (Blueprint $table) {
            $table->dropForeign(['one_off_access_id']);
            $table->dropColumn('one_off_access_id');
        });
    }
};
