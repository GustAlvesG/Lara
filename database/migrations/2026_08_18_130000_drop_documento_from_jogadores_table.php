<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O cadastro do jogador guarda apenas nome e data de nascimento como dado
 * pessoal. Documento (RG/CPF) não é usado em lugar nenhum do Placar — nem
 * para entrar em quadra, nem no telão, nem no scout — e guardá-lo sem uso
 * só cria dado pessoal para proteger.
 *
 * A coluna é apagada com o que houver nela: `down()` recria a coluna, mas
 * não devolve os valores.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jogadores', function (Blueprint $table) {
            $table->dropColumn('documento');
        });
    }

    public function down(): void
    {
        Schema::table('jogadores', function (Blueprint $table) {
            $table->string('documento')->nullable()->after('data_nascimento');
        });
    }
};
