<?php

namespace App\Http\Controllers\Replay;

use App\Http\Controllers\Controller;
use App\Models\Replay\Video;
use App\Services\Replay\MediaService;
use App\Services\Replay\RemoteStorageUnavailable;
use App\Services\Replay\RemoteVideoStore;
use Illuminate\Http\RedirectResponse;

/**
 * O clipe cuja cópia local sumiu: traz de novo do FTP e manda para o
 * arquivo estático.
 *
 * Pública, como a galeria da quadra que a usa — o endereço é o `uuid`, que
 * não se adivinha. Clipe vencido é 404 mesmo antes do expurgo: a promessa é
 * de sete dias, e o FTP não deve ser acordado por um link velho.
 *
 * Redireciona em vez de entregar o arquivo pelo PHP: o estático é o que dá
 * range request ao player.
 */
class VideoFileController extends Controller
{
    public function show(string $uuid, RemoteVideoStore $remote): RedirectResponse
    {
        $video = Video::available()->where('uuid', $uuid)->firstOrFail();

        try {
            $localPath = $remote->ensureLocal($video);
        } catch (RemoteStorageUnavailable) {
            abort(503, 'Servidor de arquivos indisponível. Tente de novo em instantes.');
        }

        abort_unless($localPath, 404);

        return redirect()->to(MediaService::url($localPath));
    }
}
