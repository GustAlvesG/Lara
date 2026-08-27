<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vídeo curto de apresentação do jogador — o telão usa a foto e o vídeo em
 * momentos diferentes (foto na escalação/súmula, vídeo na entrada em
 * quadra). Só o caminho no disco, como `foto_path`: o arquivo em si vive em
 * public/storage/placar/jogadores/{id}/, servido estaticamente.
 *
 * Aditiva e nullable — nenhum jogador existente precisa mudar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jogadores', function (Blueprint $table) {
            $table->string('video_path')->nullable()->after('foto_path');
        });
    }

    public function down(): void
    {
        Schema::table('jogadores', function (Blueprint $table) {
            $table->dropColumn('video_path');
        });
    }
};
