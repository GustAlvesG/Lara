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
        Schema::create('week_days_schedule_rule', function (Blueprint $table) {
            $table->id();
            $table->foreignId('weekday_id')->constrained('weekdays')->onDelete('cascade');
            $table->foreignId('schedule_rule_id')->constrained('schedule_rules')->onDelete('cascade');
            $table->timestamps();
        });

        #Drop the week_days column from schedule_rules table
        Schema::table('schedule_rules', function (Blueprint $table) {
            $table->dropColumn('weekdays');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('week_days_schedule_rule');
        Schema::table('schedule_rules', function (Blueprint $table) {
            $table->string('weekdays')->nullable()->after('type'); // Recreate the column
        });
    }
};
