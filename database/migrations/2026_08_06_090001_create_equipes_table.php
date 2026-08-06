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
        Schema::create('equipes', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('nome_curto')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('cidade')->nullable();
            // Registro criado às pressas pelo Node durante um jogo avulso —
            // alguém ainda precisa revisar/completar pelo cadastro web.
            $table->boolean('criado_em_campo')->default(false);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipes');
    }
};
