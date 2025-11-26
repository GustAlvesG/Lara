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
        Schema::table('schedule_rules', function (Blueprint $table) {
            #Duration can be nullable
            $table->time('duration')->nullable()->change();
            #Maximium antecedence can be nullable
            $table->integer('maximium_antecedence')->nullable()->change();

            #Rename column from maximium_antecedence to maximum_antecedence
            $table->renameColumn('maximium_antecedence', 'maximum_antecedence');
            $table->renameColumn('minimium_antecedence', 'minimum_antecedence');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedule_rules', function (Blueprint $table) {
            #Duration cannot be nullable
            $table->time('duration')->nullable(false)->change();
            #Maximium antecedence cannot be nullable
            $table->integer('maximum_antecedence')->nullable(false)->change();

            #Rename column from maximum_antecedence to maximium_antecedence
            $table->renameColumn('maximum_antecedence', 'maximium_antecedence');
            $table->renameColumn('minimum_antecedence', 'minimium_antecedence');
        });
    }
};
