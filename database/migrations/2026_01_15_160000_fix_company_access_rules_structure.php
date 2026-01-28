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
        // Fix nullable company_worker_id
        Schema::table('company_access_rules', function (Blueprint $table) {
            $table->unsignedBigInteger('company_worker_id')->nullable()->change();
        });

        // Recreate the pivoted table with correct column and foreign key
        Schema::dropIfExists('week_days_company_access_rule');

        Schema::create('week_days_company_access_rule', function (Blueprint $table) {
            $table->id();
            $table->foreignId('weekday_id')->constrained('weekdays')->onDelete('cascade');
            $table->foreignId('company_access_rule_id')->constrained('company_access_rules')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('week_days_company_access_rule');

        // Restore broken table definition (optional, but good for rollback consistency)
        Schema::create('week_days_company_access_rule', function (Blueprint $table) {
            $table->id();
            $table->foreignId('weekday_id')->constrained('weekdays')->onDelete('cascade');
            // This was the erroneous column
            $table->foreignId('schedule_rule_id')->constrained('schedule_rules')->onDelete('cascade');
            $table->timestamps();
        });

        Schema::table('company_access_rules', function (Blueprint $table) {
            // Cannot easily revert nullable change without knowing previous state for sure, 
            // but we know it was not nullable.
            // Be careful if existing data has nulls now.
            // We'll leave it nullable in down() or try to revert if possible.
            // $table->unsignedBigInteger('company_worker_id')->nullable(false)->change(); 
        });
    }
};
