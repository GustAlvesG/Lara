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
        Schema::create('data_infos', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->longText('description');
            $table->string('image')->nullable();
            $table->string('category')->nullable();
            $table->string('name_price')->nullable();
            $table->string('price_associated')->nullable();
            $table->string('price_not_associated')->nullable();
            $table->string('slots')->nullable();
            $table->string('status')->nullable();
            $table->string('location')->nullable();

            //Foreign key to information table
            $table->unsignedBigInteger('information_id');
            $table->foreign('information_id')->references('id')->on('information');

            //Foreign key to users table
            $table->unsignedBigInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users');

            // //Foreign key to auto rela
            // $table->unsignedBigInteger('before_data')->nullable();
            // $table->foreign('before_data')->references('id')->on('data_info');

            // Soft delete
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_infos');
    }
};
