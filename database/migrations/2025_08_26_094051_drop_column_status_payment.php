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
        //Drop the status_payment column from the schedules table
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn('status_payment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //Add the status_payment column back to the schedules table
        Schema::table('schedules', function (Blueprint $table) {
            $table->string('status_payment')->default('unpaid');
        });
    }
};
