<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Ftp\FtpConnectionException;
use League\Flysystem\UnableToReadFile;

class FtpController extends Controller
{
    /** Disco do FTP das câmeras, de onde a foto vem. */
    const SOURCE_DISK = 'ftp';

    /** Disco local onde a cópia fica — é dele que a tela lê (public/storage/img_car). */
    const LOCAL_DISK = 'img_car';

    /** Onde as fotos eram gravadas antes: disco `public`, que nenhuma URL alcança. */
    const LEGACY_DISK = 'public';
    const LEGACY_DIR = 'img_car';

    /** Com o FTP fora do ar, as buscas seguintes não esperam o timeout de novo. */
    const OFFLINE_CACHE_KEY = 'siv:ftp-offline';
    const OFFLINE_SECONDS = 60;

    /**
     * Garante a foto do acesso no disco local e devolve o caminho relativo
     * dela (`PLACA/arquivo.jpg`), ou false quando não há foto para mostrar.
     *
     * Só vai ao FTP na primeira vez: a foto já baixada é servida do disco.
     */
    public static function getImage($imageName)
    {
        $path = ltrim(str_replace('\\', '/', trim((string) $imageName)), '/');

        if ($path === '') {
            return false;
        }

        try {
            $local = Storage::disk(self::LOCAL_DISK);

            if ($local->exists($path)) {
                return $path;
            }

            if (self::recoverLegacyCopy($path)) {
                return $path;
            }

            if (Cache::has(self::OFFLINE_CACHE_KEY)) {
                return false;
            }

            $stream = self::openRemote($path);

            if (! is_resource($stream)) {
                // Esperado em datas antigas: o FTP só guarda as fotos recentes.
                Log::debug('SIV: foto não encontrada no FTP.', ['file' => $path]);

                return false;
            }

            // Grava com outro nome e só renomeia no fim: um download cortado
            // no meio não vira "foto já baixada" nas buscas seguintes.
            $partial = $path . '.part';

            try {
                $local->writeStream($partial, $stream);
                $local->move($partial, $path);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }

                if ($local->exists($partial)) {
                    $local->delete($partial);
                }
            }

            return $path;
        } catch (FtpConnectionException $e) {
            Cache::put(self::OFFLINE_CACHE_KEY, true, self::OFFLINE_SECONDS);
            Log::warning('SIV: FTP das câmeras fora do ar; fotos suspensas por ' . self::OFFLINE_SECONDS . 's.', [
                'file' => $path,
                'error' => $e->getMessage(),
            ]);

            return false;
        } catch (\Throwable $e) {
            Log::warning('SIV: não foi possível trazer a foto do FTP.', [
                'file' => $path,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * URL pública da foto devolvida por getImage(). Cada trecho é codificado
     * porque os nomes vêm da câmera com espaço e `&` ("Sem placa/…&Prata&A.jpg").
     */
    public static function imageUrl($path)
    {
        $segments = array_map('rawurlencode', explode('/', (string) $path));

        return asset('storage/img_car/' . implode('/', $segments));
    }

    /**
     * Abre a foto no FTP, ou devolve null quando ela não está lá.
     *
     * Desde 26/08/2026 o banco registra o nome com `.vehicleBody` antes da
     * extensão, e no FTP o arquivo continua só `.jpg`: tenta o nome como veio
     * e, não achando, sem esse trecho. Falha de conexão sobe para quem chamou.
     */
    private static function openRemote(string $path)
    {
        $ftp = Storage::disk(self::SOURCE_DISK);

        foreach (array_unique([$path, str_replace('.vehicleBody.', '.', $path)]) as $candidate) {
            try {
                $stream = $ftp->readStream($candidate);
            } catch (UnableToReadFile $e) {
                continue;
            }

            if (is_resource($stream)) {
                return $stream;
            }
        }

        return null;
    }

    /**
     * Traz para o disco certo a foto baixada antes da correção, que ficou em
     * storage/app/public/img_car. Evita ir ao FTP por algo que já está aqui.
     */
    private static function recoverLegacyCopy(string $path): bool
    {
        $legacy = Storage::disk(self::LEGACY_DISK);
        $legacyPath = self::LEGACY_DIR . '/' . $path;

        if (! $legacy->exists($legacyPath)) {
            return false;
        }

        $stream = $legacy->readStream($legacyPath);

        if (! is_resource($stream)) {
            return false;
        }

        try {
            return (bool) Storage::disk(self::LOCAL_DISK)->writeStream($path, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }
}
