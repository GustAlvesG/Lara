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
        Schema::create('access_rules', function (Blueprint $table) {
            $table->id();
            #Start date and end date can be null
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            #Start time and end time can be null
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();

            #Weekdays can be null
            $table->string('weekdays')->nullable();

            #Status can be boolean
            $table->boolean('status');

            #Company can be null
            $table->unsignedBigInteger('company_id')->nullable();
            $table->foreign('company_id')->references('id')->on('companies');

            #Outer can be null
            $table->unsignedBigInteger('outer_id')->nullable();
            $table->foreign('outer_id')->references('id')->on('outers');

           
            $table->timestamps();
             # Soft delete
             $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('access_rules');
    }
};
