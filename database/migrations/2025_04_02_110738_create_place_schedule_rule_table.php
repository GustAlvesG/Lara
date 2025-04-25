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

        //Remove foreign key from place_group_id in schedule_rules table
        Schema::table('schedule_rules', function (Blueprint $table) {
            $table->dropForeign(['place_group_id']);
            $table->dropColumn('place_group_id');
        });

        Schema::create('place_schedule_rule', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('place_id');
            $table->unsignedBigInteger('schedule_rule_id');
            $table->timestamps();

            $table->foreign('place_id')->references('id')->on('places')->onDelete('cascade');
            $table->foreign('schedule_rule_id')->references('id')->on('schedule_rules')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('place_schedule_rule');
    }
};
