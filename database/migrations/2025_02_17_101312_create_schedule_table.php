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
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('place_id');
            $table->foreign('place_id')->references('id')->on('places');

            $table->datetime('start_schedule');
            $table->datetime('end_schedule');

            $table->string('associated_name')->nullable();
            $table->string('associated_cpf')->nullable();
            $table->string('associated_telephone')->nullable();

            $table->string("description")->nullable();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
