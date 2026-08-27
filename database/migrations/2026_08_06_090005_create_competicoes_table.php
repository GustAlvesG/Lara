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
        Schema::create('competicoes', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->foreignId('modalidade_id')->constrained('modalidades')->restrictOnDelete();
            $table->year('temporada');
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('competicoes');
    }
};
