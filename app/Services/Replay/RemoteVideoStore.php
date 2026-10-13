<?php

namespace App\Services\Replay;

use App\Models\Replay\Video;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use League\Flysystem\FilesystemException;
use League\Flysystem\Ftp\FtpConnectionException;
use League\Flysystem\UnableToReadFile;

/**
 * Os clipes no servidor de arquivos (FTP) e a cópia local deles.
 *
 * O sistema de captura grava o clipe no FTP e informa o caminho; o Lara
 * traz uma cópia para `public/storage/replay/videos` (disco `replay`), e é
 * ela que o servidor web entrega — FTP não fala HTTP, e a cópia local é o
 * que mantém o *range request* que deixa o sócio avançar o vídeo.
 *
 * A cópia é feita na recepção, porque o tipo do arquivo é conferido pelos
 * bytes e o adaptador FTP só lê o arquivo inteiro: o download que valida é o
 * mesmo que serve. Se a cópia sumir, `ensureLocal()` refaz a partir do FTP.
 */
class RemoteVideoStore
{
    const DISK = 'replay_ftp';

    /** Com o FTP fora, os pedidos seguintes não esperam o timeout de novo. */
    const OFFLINE_CACHE_KEY = 'replay:ftp-offline';
    const OFFLINE_SECONDS = 60;

    public static function disk(): Filesystem
    {
        return Storage::disk(self::DISK);
    }

    /**
     * O caminho informado pelo sistema de captura, relativo à pasta do
     * Replay no FTP.
     *
     * Aceita o caminho com ou sem a pasta raiz na frente ("Lara/Repet/…" ou
     * só "…"): os dois chegam ao mesmo arquivo, e é este o que se grava.
     * Recusa `..`: o caminho vem de fora e é usado para apagar no expurgo.
     */
    public function normalizePath(string $path): string
    {
        $path = trim(str_replace('\\', '/', trim($path)), '/');
        $root = trim(str_replace('\\', '/', (string) config('filesystems.disks.' . self::DISK . '.root', '')), '/');

        if ($root !== '' && str_starts_with(strtolower($path . '/'), strtolower($root . '/'))) {
            $path = ltrim(substr($path, strlen($root)), '/');
        }

        $segments = explode('/', $path);

        if ($path === '' || in_array('..', $segments, true) || in_array('.', $segments, true) || in_array('', $segments, true)) {
            throw new InvalidArgumentException('Caminho do clipe no FTP inválido.');
        }

        return $path;
    }

    /**
     * Baixa o clipe do FTP para o disco local, em `$localPath`.
     *
     * Grava com outro nome e só renomeia no fim: um download cortado no meio
     * não vira "cópia pronta".
     *
     * @throws InvalidArgumentException  o arquivo não está no FTP
     * @throws RemoteStorageUnavailable  o FTP não respondeu
     */
    public function download(string $remotePath, string $localPath): void
    {
        $this->guardOffline();

        $stream = $this->remote(function (Filesystem $ftp) use ($remotePath) {
            if (! $ftp->exists($remotePath)) {
                return null;
            }

            try {
                return $ftp->readStream($remotePath);
            } catch (UnableToReadFile) {
                return null;
            }
        });

        if (! is_resource($stream)) {
            throw new InvalidArgumentException("Clipe não encontrado no FTP: {$remotePath}");
        }

        $local = MediaService::disk();
        $partial = $localPath . '.part';

        try {
            $local->writeStream($partial, $stream);
            $local->delete($localPath);
            $local->move($partial, $localPath);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }

            if ($local->exists($partial)) {
                $local->delete($partial);
            }
        }
    }

    /**
     * Garante a cópia local do clipe e devolve o caminho dela no disco
     * `replay`, ou null quando o clipe não está mais no FTP.
     *
     * @throws RemoteStorageUnavailable
     */
    public function ensureLocal(Video $video): ?string
    {
        $localPath = $video->local_path;

        if ($localPath && MediaService::disk()->exists($localPath)) {
            return $localPath;
        }

        // Linha sem local_path é anterior à troca para o FTP: não há como
        // saber a extensão nem onde a cópia deveria morar.
        if (! $localPath) {
            return null;
        }

        try {
            $this->download($video->file_path, $localPath);
        } catch (InvalidArgumentException) {
            Log::warning('Replay: clipe sem arquivo no FTP.', ['video' => $video->uuid, 'file' => $video->file_path]);

            return null;
        }

        return $localPath;
    }

    /**
     * Apaga o clipe do FTP e a cópia local. Clipe que já não estava no FTP
     * não é erro: devolve false para o expurgo contar.
     *
     * @throws RemoteStorageUnavailable  nada foi apagado do FTP; o registro deve ficar
     */
    public function delete(Video $video): bool
    {
        $existed = false;

        if ($video->local_path === null) {
            // Anterior à troca: o arquivo, se houver, está só no disco local.
            if ($video->file_path && MediaService::disk()->exists($video->file_path)) {
                MediaService::disk()->delete($video->file_path);
                $existed = true;
            }

            return $existed;
        }

        $this->guardOffline();

        $existed = $this->remote(function (Filesystem $ftp) use ($video) {
            if (! $ftp->exists($video->file_path)) {
                return false;
            }

            $ftp->delete($video->file_path);

            return true;
        });

        if (MediaService::disk()->exists($video->local_path)) {
            MediaService::disk()->delete($video->local_path);
        }

        return $existed;
    }

    /**
     * Roda uma operação no FTP e traduz falha de servidor em
     * RemoteStorageUnavailable, marcando o FTP como fora por um minuto.
     */
    private function remote(callable $operation): mixed
    {
        try {
            return $operation(self::disk());
        } catch (FtpConnectionException $e) {
            $this->markOffline($e);
        } catch (FilesystemException $e) {
            // Com `throw` ligado, qualquer outra falha do driver (apagar,
            // ler metadados) também é o servidor recusando, não o arquivo
            // faltando — esse caso já foi tratado pelo `exists()`.
            Log::warning('Replay: falha no FTP dos clipes.', ['error' => $e->getMessage()]);

            throw new RemoteStorageUnavailable('O servidor de arquivos recusou a operação.', 0, $e);
        }
    }

    private function guardOffline(): void
    {
        if (Cache::has(self::OFFLINE_CACHE_KEY)) {
            throw new RemoteStorageUnavailable('Servidor de arquivos fora do ar.');
        }
    }

    private function markOffline(\Throwable $e): never
    {
        Cache::put(self::OFFLINE_CACHE_KEY, true, self::OFFLINE_SECONDS);
        Log::warning('Replay: FTP dos clipes fora do ar; acesso suspenso por ' . self::OFFLINE_SECONDS . 's.', [
            'error' => $e->getMessage(),
        ]);

        throw new RemoteStorageUnavailable('Servidor de arquivos fora do ar.', 0, $e);
    }
}
