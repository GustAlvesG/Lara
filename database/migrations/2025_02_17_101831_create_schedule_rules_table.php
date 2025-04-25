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
        Schema::create('schedule_rules', function (Blueprint $table) {
            $table->id();
            // Rule status
            $table->boolean('status');

            // Schedule Duration
            $table->time('duration');

            // Weekdays can be scheduled
            $table->string('weekdays');

            // Antecedence to schedule
            $table->string('antecedence');

            // Time can be schedule
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();

            // Date cannot be schedule // BLOCK!!!!
            #Start date and end date can be null
            $table->date('start_date_block')->nullable();
            $table->date('end_date_block')->nullable();

            //Group Place
            #Group
            $table->unsignedBigInteger('group_id');
            $table->foreign('group_id')->references('id')->on('place_groups');

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_rules');
    }
};
