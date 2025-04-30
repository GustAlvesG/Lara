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
            // Change antecedence to can be null
            $table->integer('antecedence')->nullable()->change();
            // Change quantity to can be null
            $table->integer('quantity')->nullable()->change();
            // Change duration to can be null
            $table->time('duration')->nullable()->change();
            // Change interval to can be null
            $table->time('interval')->nullable()->change();



            // Add name column to schedule_rules table
            $table->string('name')->nullable()->after('id');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedule_rules', function (Blueprint $table) {
            // Change antecedence to cannot be null
            $table->integer('antecedence')->default(0)->change();
            // Change quantity to cannot be null
            $table->integer('quantity')->default(0)->change();

            // Change duration to default null
            $table->time('duration')->nullable()->change();
            // Change interval to default null
            $table->time('interval')->nullable()->change();

            // Drop name column from schedule_rules table
            $table->dropColumn('name');
        });
    }
};
