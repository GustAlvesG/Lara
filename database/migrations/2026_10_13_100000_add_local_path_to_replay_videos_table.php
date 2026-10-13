<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Os clipes passaram a morar no servidor de arquivos (FTP).
     *
     * `file_path` agora é o caminho do clipe no FTP, relativo à pasta do
     * Replay (disco `replay_ftp`) — é o que o sistema de captura informa.
     * `local_path` é a cópia que o Lara guarda em `public/storage` para o
     * servidor web entregar com *range request*; ela nasce na recepção e é
     * refeita a partir do FTP se sumir.
     */
    public function up(): void
    {
        Schema::table('replay_videos', function (Blueprint $table) {
            $table->string('local_path')->nullable()->after('file_path');
        });
    }

    public function down(): void
    {
        Schema::table('replay_videos', function (Blueprint $table) {
            $table->dropColumn('local_path');
        });
    }
};
