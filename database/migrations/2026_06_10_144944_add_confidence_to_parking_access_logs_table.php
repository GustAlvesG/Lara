<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('parking_access_logs', function (Blueprint $table) {
            $table->unsignedTinyInteger('confidence')->nullable()->after('time_entry');
        });
    }

    public function down(): void
    {
        Schema::table('parking_access_logs', function (Blueprint $table) {
            $table->dropColumn('confidence');
        });
    }
};
