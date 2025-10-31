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
        //Rename column antecedence to minimin_antecedence in the access_rules table
        Schema::table('schedule_rules', function (Blueprint $table) {
            $table->renameColumn('antecedence', 'minimium_antecedence');
        });

        //Add column antecedence to the schedule_rules table
        Schema::table('schedule_rules', function (Blueprint $table) {
            $table->integer('maximium_antecedence')->default(0)->after('minimium_antecedence');
        });

        //Add default value to duration 01:00:00
        Schema::table('schedule_rules', function (Blueprint $table) {
            $table->time('duration')->default('01:00:00')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //Rename column minimin_antecedence to antecedence in the schedule_rules table
        Schema::table('schedule_rules', function (Blueprint $table) {
            $table->renameColumn('minimium_antecedence', 'antecedence');
        });

        //Drop the maximium_antecedence column from the schedule_rules table
        Schema::table('schedule_rules', function (Blueprint $table) {
            $table->dropColumn('maximium_antecedence');
        });

        //Remove default value from duration
        Schema::table('schedule_rules', function (Blueprint $table) {
            $table->time('duration')->default(null)->change();
        });
    }
};
