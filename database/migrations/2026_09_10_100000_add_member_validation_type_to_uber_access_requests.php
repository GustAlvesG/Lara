<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('uber_access_requests', function (Blueprint $table) {
            // Quem conferiu: 'socio' ou 'funcionario'. Nulo quando não conferiu.
            $table->string('member_validation_type')->nullable()->after('member_validation_name');
        });
    }

    public function down(): void
    {
        Schema::table('uber_access_requests', function (Blueprint $table) {
            $table->dropColumn('member_validation_type');
        });
    }
};
