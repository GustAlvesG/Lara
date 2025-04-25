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
        Schema::table('data_infos', function (Blueprint $table) {
            $table->string('day_hour')->nullable()->after('slots');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('data_infos', 'day_hour')) {
            Schema::table('data_infos', function (Blueprint $table) {
                $table->dropColumn('day_hour');
            });
        }
    }
};
