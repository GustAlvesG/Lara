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
        Schema::create('times', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipe_id')->constrained('equipes')->cascadeOnDelete();
            // Modalidade é referência fixa (3 linhas) — nunca deve ser apagada
            // enquanto houver time vinculado.
            $table->foreignId('modalidade_id')->constrained('modalidades')->restrictOnDelete();
            $table->string('categoria');
            // Se null, a API/telas montam a partir de equipe + categoria.
            $table->string('nome_exibicao')->nullable();
            // Se null, herda a logo da equipe — resolvido no Resource, não aqui.
            $table->string('logo_path')->nullable();
            $table->boolean('criado_em_campo')->default(false);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['equipe_id', 'modalidade_id', 'categoria']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('times');
    }
};
