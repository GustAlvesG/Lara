<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Dono dos tokens do Sanctum usados pelo Node — deliberadamente fora da
     * tabela `users`: é uma credencial de máquina, não uma pessoa, e não deve
     * aparecer na listagem de usuários nem herdar nada de lá (roles,
     * permissões, login por sessão).
     */
    public function up(): void
    {
        Schema::create('placar_api_clientes', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('placar_api_clientes');
    }
};
