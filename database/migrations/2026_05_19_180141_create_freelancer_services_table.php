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
        Schema::create('freelancer_services', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('freelancer_id');
            $table->foreign('freelancer_id')->references('id')->on('freelancers');
            $table->unsignedBigInteger('function_freelancer_id');
            $table->foreign('function_freelancer_id')->references('id')->on('function_freelancers');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->decimal('price', 8, 2);
            $table->integer('total_hours');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('freelancer_services');
    }
};
