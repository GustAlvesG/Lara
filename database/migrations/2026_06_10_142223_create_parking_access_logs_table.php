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
        Schema::create('parking_access_logs', function (Blueprint $table) {
            $table->id();
            $table->string('plate', 20);
            $table->string('camera', 100);
            $table->dateTime('time_entry');
            $table->boolean('authorized');
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['plate', 'time_entry']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parking_access_logs');
    }
};
