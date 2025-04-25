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
        //Add status type if not exists
        Schema::table('schedule_rules', function (Blueprint $table) {
            $table->string('type')->default('include');
        });

        Schema::table('access_rules', function (Blueprint $table) {
            $table->string('type')->default('include');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedule_rules', function (Blueprint $table) {
            $table->dropColumn('type');
        });

        Schema::table('access_rules', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
