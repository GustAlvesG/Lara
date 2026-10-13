<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    /**
     * Descarta os clipes recebidos antes da troca para o FTP.
     *
     * Decisão de quem opera (13/10/2026): os vídeos que chegaram por upload
     * não vão para o FTP; podem ser apagados. São reconhecidos por não ter
     * `local_path` — a coluna nasceu na migration anterior, e todo clipe
     * recebido depois dela a preenche.
     *
     * Só alcança `replay_videos` e os arquivos dessas linhas no disco
     * `replay`. Arquivo primeiro, registro depois, como no expurgo.
     *
     * Não há volta: o arquivo apagado não se recupera.
     */
    public function up(): void
    {
        $disk = Storage::disk('replay');

        DB::table('replay_videos')
            ->whereNull('local_path')
            ->orderBy('id')
            ->chunkById(200, function ($videos) use ($disk) {
                foreach ($videos as $video) {
                    if ($video->file_path && $disk->exists($video->file_path)) {
                        $disk->delete($video->file_path);
                    }

                    DB::table('replay_videos')->where('id', $video->id)->delete();
                }
            });
    }

    public function down(): void
    {
        // Irreversível: os arquivos não existem mais.
    }
};
