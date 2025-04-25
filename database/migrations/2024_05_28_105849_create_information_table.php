<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
// Soft delete


return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('information', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->longText('description');
            $table->string('image')->nullable()->default('disabled');
            $table->string('category')->nullable()->default('disabled');
            $table->decimal('price_associated', 8, 2)->nullable()->default(0.00);
            $table->decimal('price_not_associated', 8, 2)->nullable()->default(0.00);

            $table->string('slots')->nullable()->default('disabled');
            $table->string('status')->nullable()->default('disabled');
            $table->string('location')->nullable()->default('disabled');
            $table->timestamps();
            // Soft delete
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('information');
    }
};
