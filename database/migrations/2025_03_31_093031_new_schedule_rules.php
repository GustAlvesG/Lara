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
        //Drop the old table if it exists
        Schema::dropIfExists('schedule_rules');

        //Create the new table
        Schema::create('schedule_rules', function (Blueprint $table) {
            $table->id();
            
            // Rule status
            $table->boolean('status');
            
            //Type can be inclusivo or exclusivo
            $table->string('type')->default('include');

            // Schedule Duration
            $table->time('duration')->nullable();

            // Weekdays can be scheduled
            $table->string('weekdays')->nullable();

            // Antecedence to schedule
            $table->integer('antecedence')->default(1);

            $table->time('interval')->nullable();

            // Time can be schedule
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();

            // Dates can be scheduled
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            //Quantity of schedules by day
            $table->integer('quantity')->default(2);



            //Group Place
            #Group
            $table->unsignedBigInteger('place_group_id');
            $table->foreign('place_group_id')->references('id')->on('place_groups');

            $table->softDeletes();
            $table->timestamps();
        });

        //Rename column on places
        Schema::table('places', function (Blueprint $table) {
            $table->renameColumn('group_id', 'place_group_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //Drop the new table if it exists
        Schema::dropIfExists('schedule_rules');

        Schema::create('schedule_rules', function (Blueprint $table) {
            $table->id();
            // Rule status
            $table->boolean('status');

            // Schedule Duration
            $table->time('duration');

            // Weekdays can be scheduled
            $table->string('weekdays');

            // Antecedence to schedule
            $table->string('antecedence')->nullable();

            // Time can be schedule
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();

            // Date cannot be schedule // BLOCK!!!!
            #Start date and end date can be null
            $table->date('start_date_block')->nullable();
            $table->date('end_date_block')->nullable();

            $table->string('type')->default('include');

            //Group Place
            #Group
            $table->unsignedBigInteger('group_id');
            $table->foreign('group_id')->references('id')->on('place_groups');

            $table->softDeletes();
            $table->timestamps();
        });

        //Rename column on places
        Schema::table('places', function (Blueprint $table) {
            $table->renameColumn('place_group_id', 'group_id');
        });
    }
};
