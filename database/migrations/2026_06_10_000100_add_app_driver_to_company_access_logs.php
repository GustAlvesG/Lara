<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_access_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('app_driver_id')->nullable()->after('company_worker_id');
            $table->foreign('app_driver_id')->references('id')->on('app_drivers')->onDelete('set null');
            $table->string('obs')->nullable()->after('target');
        });
    }

    public function down(): void
    {
        Schema::table('company_access_logs', function (Blueprint $table) {
            $table->dropForeign(['app_driver_id']);
            $table->dropColumn(['app_driver_id', 'obs']);
        });
    }
};
