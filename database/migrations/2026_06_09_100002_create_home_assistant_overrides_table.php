<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_assistant_overrides', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contactor_id');
            $table->foreign('contactor_id')->references('id')->on('contactors')->cascadeOnDelete();
            // manual_on | manual_off | schedule_override
            $table->enum('mode', ['manual_on', 'manual_off', 'schedule_override']);
            $table->time('turn_on_at')->nullable();
            $table->time('turn_off_at')->nullable();
            $table->dateTime('expires_at');
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by');
            $table->foreign('created_by')->references('id')->on('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_assistant_overrides');
    }
};
