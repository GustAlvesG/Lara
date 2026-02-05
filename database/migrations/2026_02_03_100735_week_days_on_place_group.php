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

     Schema::dropIfExists('week_days_place_group');

        Schema::create('week_days_place_group', function (Blueprint $table) {
            $table->id();
            $table->foreignId('weekday_id')->constrained('weekdays')->onDelete('cascade');
            // This was the erroneous column
            $table->foreignId('place_group_id')->constrained('place_groups')->onDelete('cascade');
            $table->timestamps();
        });

        Schema::table('place_groups', function (Blueprint $table) {
            $table->time('start_time_sales')->nullable()->default(null);
            $table->time('end_time_sales')->nullable()->default(null);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('week_days_place_group');

        Schema::table('place_groups', function (Blueprint $table) {
            $table->dropColumn('start_time_sales');
            $table->dropColumn('end_time_sales');
        });
    }
};
