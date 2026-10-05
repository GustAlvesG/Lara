<?php

namespace App\Services\Replay;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Ponto único de verdade sobre a mídia do Replay: onde grava, como vira URL e
 * o que é um arquivo aceitável.
 *
 * O disco aponta para dentro de `public/` (ver config/filesystems.php): o
 * clipe precisa ser servido como arquivo estático pelo servidor web, sem
 * passar por PHP, porque é isso que dá *range request* — e sem range request
 * o sócio não consegue avançar o vídeo no player, só assistir do começo.
 *
 * Logos são só PNG e GIF, por decisão de quem opera: as duas são as únicas
 * com transparência que o Marketing usa, e restringir aqui evita JPEG com
 * fundo branco entrando no overlay.
 */
class MediaService
{
    const DISK = 'replay';

    const LOGO_DIR = 'replay/logos';
    const OVERLAY_DIR = 'replay/overlays';
    const VIDEO_DIR = 'replay/videos';

    const MAX_LOGO_BYTES = 8 * 1024 * 1024;

    /** Lado maior de uma logo depois de normalizada. */
    const MAX_LOGO_SIDE = 1920;

    const LOGO_MIMES = [
        'image/png' => 'png',
        'image/gif' => 'gif',
    ];

    /**
     * URL pública de um caminho de mídia do Replay.
     *
     * `asset()` e não `Storage::url()`: o disco do Laravel monta a URL a
     * partir do APP_URL fixo no .env, e o app servido em outro host/porta
     * sairia com todo link apontando para o lugar errado. `asset()` resolve
     * pela request em curso — mesma decisão já tomada no ImagemService.
     */
    public static function url(?string $path): ?string
    {
        return $path ? asset('storage/' . ltrim($path, '/')) : null;
    }

    /** Caminho absoluto no disco — o renderer precisa dele para o ffmpeg. */
    public static function absolutePath(string $path): string
    {
        return Storage::disk(self::DISK)->path($path);
    }

    public static function disk()
    {
        return Storage::disk(self::DISK);
    }

    /**
     * Valida e guarda uma logomarca enviada pela tela de layouts.
     *
     * @return array{path: string, animated: bool, width: int, height: int}
     */
    public function storeLogo(UploadedFile $file): array
    {
        $binary = file_get_contents($file->getRealPath());

        if ($binary === false || $binary === '') {
            throw new InvalidArgumentException('Não foi possível ler o arquivo enviado.');
        }

        if (strlen($binary) > self::MAX_LOGO_BYTES) {
            throw new InvalidArgumentException('Imagem maior que 8MB.');
        }

        // O tipo sai dos BYTES REAIS, nunca do Content-Type ou da extensão do
        // nome — os dois são declarados pelo cliente e não provam nada.
        $info = @getimagesizefromstring($binary);
        if ($info === false) {
            throw new InvalidArgumentException('Arquivo não é uma imagem válida.');
        }

        $mime = $info['mime'] ?? '';
        if (!isset(self::LOGO_MIMES[$mime])) {
            throw new InvalidArgumentException('Formato não aceito — envie PNG ou GIF.');
        }

        $extension = self::LOGO_MIMES[$mime];
        $animated = $extension === 'gif' && $this->isAnimatedGif($binary);

        // GIF animado vai como veio: redimensionar em GD achataria os quadros
        // e a logo chegaria parada no overlay. PNG é normalizado para não
        // carregar um arquivo de 4000px que o overlay vai encolher de todo
        // jeito.
        if ($extension === 'png') {
            $binary = $this->shrinkPng($binary);
            $info = @getimagesizefromstring($binary) ?: $info;
        }

        $path = self::LOGO_DIR . '/' . uniqid('logo_', true) . '.' . $extension;
        self::disk()->put($path, $binary);

        return [
            'path' => $path,
            'animated' => $animated,
            'width' => (int) ($info[0] ?? 0),
            'height' => (int) ($info[1] ?? 0),
        ];
    }

    public function remove(?string $path): void
    {
        if ($path) {
            self::disk()->delete($path);
        }
    }

    /**
     * GIF animado tem mais de um bloco de controle gráfico (0x21F904). Um
     * único bloco — ou nenhum — é imagem parada.
     *
     * É a forma barata de responder: GD não expõe contagem de quadros, e
     * abrir o arquivo inteiro só para contar seria caro num upload de 8MB.
     */
    private function isAnimatedGif(string $binary): bool
    {
        return substr_count($binary, "\x00\x21\xF9\x04") > 1;
    }

    /** Encolhe o PNG para caber em MAX_LOGO_SIDE, preservando o alfa. */
    private function shrinkPng(string $binary): string
    {
        $source = @imagecreatefromstring($binary);
        if ($source === false) {
            throw new InvalidArgumentException('Não foi possível ler a imagem — arquivo corrompido?');
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(self::MAX_LOGO_SIDE / $width, self::MAX_LOGO_SIDE / $height, 1);

        if ($scale >= 1) {
            imagedestroy($source);

            return $binary;
        }

        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        $target = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagefill($target, 0, 0, imagecolorallocatealpha($target, 0, 0, 0, 127));
        imagealphablending($target, true);

        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        ob_start();
        imagepng($target, null, 6);
        $encoded = ob_get_clean();

        imagedestroy($source);
        imagedestroy($target);

        return $encoded;
    }
}
