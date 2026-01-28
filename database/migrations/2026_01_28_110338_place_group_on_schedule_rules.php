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
        //Add columns on place_groups table
        Schema::table('place_groups', function (Blueprint $table) {
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('minimum_antecedence')->default(0);
            $table->integer('maximum_antecedence')->default(0);
            $table->time('duration')->default('01:00:00');
            $table->time('interval')->default('00:00:00');
            $table->integer('daily_limit')->default(1);
        });

        Schema::table('schedule_rules', function (Blueprint $table) {
            $table->dropColumn('minimum_antecedence');
            $table->dropColumn('maximum_antecedence');
            $table->dropColumn('duration');
            $table->dropColumn('interval');
            $table->dropColumn('quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //Drop columns from place_groups table
        Schema::table('place_groups', function (Blueprint $table) {
            $table->dropColumn('start_time');
            $table->dropColumn('end_time');
            $table->dropColumn('minimum_antecedence');
            $table->dropColumn('maximum_antecedence');
            $table->dropColumn('duration');
            $table->dropColumn('interval');
            $table->dropColumn('daily_limit');
        });

        Schema::table('schedule_rules', function (Blueprint $table) {
            $table->integer('minimum_antecedence')->default(0);
            $table->integer('maximum_antecedence')->default(0);
            $table->time('duration')->default('01:00:00');
            $table->time('interval')->default('00:00:00');
            $table->integer('quantity')->default(1);
        });
    }
};
