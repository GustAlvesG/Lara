<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comp_time_imports', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->enum('phase', ['detecting', 'importing', 'confirming'])->default('detecting');
            $table->string('temp_file_path');
            $table->json('result_data')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comp_time_imports');
    }
};
