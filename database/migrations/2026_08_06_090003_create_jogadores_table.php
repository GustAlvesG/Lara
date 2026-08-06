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
        Schema::create('jogadores', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            // Nome curto exibido no telão — se null, cai para `nome`.
            $table->string('nome_exibicao')->nullable();
            $table->string('foto_path')->nullable();
            $table->date('data_nascimento')->nullable();
            $table->string('documento')->nullable();
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
        Schema::dropIfExists('jogadores');
    }
};
