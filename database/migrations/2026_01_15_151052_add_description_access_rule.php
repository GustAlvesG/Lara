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
        Schema::table('company_access_rules', function (Blueprint $table) {
            $table->text('description')->nullable()->after('end_date');
        });

        //Tabel assocativa company_access_rules e week_days
        Schema::create('week_days_company_access_rule', function (Blueprint $table) {
            $table->id();
            $table->foreignId('weekday_id')->constrained('weekdays')->onDelete('cascade');
            $table->foreignId('schedule_rule_id')->constrained('schedule_rules')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_access_rules', function (Blueprint $table) {
            $table->dropColumn('description');
        });

        Schema::dropIfExists('company_access_rule_week_day');
        }
};
