<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Favoritos e ordem do menu de cada pessoa, guardados na conta.
 *
 * Antes viviam só no localStorage do navegador: limpar os dados do site (ou
 * trocar de computador) apagava os favoritos. Aqui eles acompanham o usuário.
 *
 * Um JSON só — `{"favorites": [...], "order": [...]}` — porque são listas
 * curtas de chaves de rota, lidas inteiras a cada tela e nunca consultadas
 * por dentro. `null` quer dizer "nunca salvou": o navegador então sobe o que
 * tiver guardado, uma vez.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('nav_preferences')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('nav_preferences');
        });
    }
};
