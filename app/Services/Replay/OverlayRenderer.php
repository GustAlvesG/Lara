<?php

namespace App\Services\Replay;

use App\Models\Replay\Layout;
use App\Models\Replay\LayoutItem;
use App\Support\Replay\Orientation;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Compõe as logomarcas de um layout no arquivo que o sistema de captura
 * queima sobre o vídeo.
 *
 * São dois produtos:
 *
 *  - PNG transparente, SEMPRE, do tamanho cheio do frame. É o contrato
 *    principal: o outro sistema aplica em (0,0) sem calcular nada.
 *  - WebM com canal alpha, só quando algum item é GIF animado. Exige ffmpeg
 *    no servidor — se não houver, o PNG (com o primeiro quadro do GIF) segue
 *    valendo e o módulo não quebra. Essa degradação é deliberada: overlay
 *    parado é um problema de estética, overlay nenhum é um problema de
 *    operação.
 *
 * O hash da composição entra no nome do arquivo. Assim cada alteração publica
 * uma URL nova, e o sistema de captura nunca precisa invalidar cache — se a
 * URL é a mesma, o conteúdo é o mesmo.
 */
class OverlayRenderer
{
    /**
     * Teto do laço animado. Um overlay de 10s cobre qualquer vinheta de
     * logomarca e mantém o arquivo pequeno — ele será repetido em looping
     * pelo sistema de captura de qualquer forma.
     */
    const MAX_LOOP_MS = 10000;

    const MIN_LOOP_MS = 1000;

    /** Quadros por segundo do overlay animado. */
    const FPS = 15;

    public function __construct(private MediaService $media)
    {
    }

    /**
     * Renderiza (ou re-renderiza) o overlay do layout e grava os caminhos.
     *
     * Layout sem itens não gera arquivo: o vídeo sai limpo, que é o
     * comportamento certo para quem apagou todas as logos.
     */
    public function render(Layout $layout): Layout
    {
        $items = $layout->items()->orderBy('z_index')->get();

        $previousPng = $layout->overlay_path;
        $previousAnimated = $layout->overlay_animated_path;

        if ($items->isEmpty()) {
            $this->media->remove($previousPng);
            $this->media->remove($previousAnimated);

            $layout->forceFill([
                'overlay_path' => null,
                'overlay_animated_path' => null,
                'overlay_hash' => null,
                'overlay_rendered_at' => now(),
            ])->save();

            return $layout;
        }

        $dimensions = Orientation::dimensions($layout->orientation);
        $hash = $this->hashFor($layout, $items, $dimensions);

        $pngPath = MediaService::OVERLAY_DIR . "/{$layout->id}-{$hash}.png";
        $this->media->disk()->put($pngPath, $this->composePng($items, $dimensions));

        $animatedPath = null;
        if ($items->contains(fn (LayoutItem $item) => $item->animated)) {
            $animatedPath = $this->composeAnimated($layout, $items, $dimensions, $hash);
        }

        // Só depois de a nova composição existir: se a renderização falhar no
        // meio, o layout continua publicando o overlay anterior em vez de
        // ficar sem nenhum.
        if ($previousPng && $previousPng !== $pngPath) {
            $this->media->remove($previousPng);
        }

        if ($previousAnimated && $previousAnimated !== $animatedPath) {
            $this->media->remove($previousAnimated);
        }

        $layout->forceFill([
            'overlay_path' => $pngPath,
            'overlay_animated_path' => $animatedPath,
            'overlay_hash' => $hash,
            'overlay_rendered_at' => now(),
        ])->save();

        return $layout;
    }

    /**
     * Identidade da composição: mudou qualquer coisa que afete o pixel final,
     * muda o hash — e com ele o nome do arquivo e a URL publicada.
     */
    private function hashFor(Layout $layout, $items, array $dimensions): string
    {
        $parts = [$layout->orientation, $dimensions['width'], $dimensions['height']];

        foreach ($items as $item) {
            $parts[] = implode(':', [
                $item->image_path,
                $item->x,
                $item->y,
                $item->width,
                $item->height,
                $item->opacity,
                $item->z_index,
                // O arquivo pode ter sido trocado mantendo o caminho.
                $this->media->disk()->exists($item->image_path)
                    ? $this->media->disk()->lastModified($item->image_path)
                    : 0,
            ]);
        }

        return substr(hash('sha256', implode('|', $parts)), 0, 12);
    }

    /** @return string bytes do PNG composto */
    private function composePng($items, array $dimensions): string
    {
        $canvas = imagecreatetruecolor($dimensions['width'], $dimensions['height']);

        // Ordem importa: desligar o blending ANTES de preencher é o que grava
        // transparência de verdade em vez de preto opaco.
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        imagealphablending($canvas, true);

        foreach ($items as $item) {
            $this->drawItem($canvas, $item, $dimensions);
        }

        ob_start();
        imagepng($canvas, null, 6);
        $encoded = ob_get_clean();

        imagedestroy($canvas);

        return $encoded;
    }

    private function drawItem($canvas, LayoutItem $item, array $dimensions): void
    {
        if (! $this->media->disk()->exists($item->image_path)) {
            Log::warning('Replay: logomarca ausente ao compor overlay.', ['path' => $item->image_path]);

            return;
        }

        $binary = $this->media->disk()->get($item->image_path);
        $source = @imagecreatefromstring($binary);

        if ($source === false) {
            Log::warning('Replay: logomarca ilegível ao compor overlay.', ['path' => $item->image_path]);

            return;
        }

        imagealphablending($source, true);
        imagesavealpha($source, true);

        // Percentual -> pixel. O arredondamento é para fora (max 1px) porque
        // uma logo de 0,4% ainda precisa aparecer.
        $targetWidth = max(1, (int) round($dimensions['width'] * $item->width / 100));
        $targetHeight = max(1, (int) round($dimensions['height'] * $item->height / 100));
        $targetX = (int) round($dimensions['width'] * $item->x / 100);
        $targetY = (int) round($dimensions['height'] * $item->y / 100);

        $scaled = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($scaled, false);
        imagesavealpha($scaled, true);
        imagefill($scaled, 0, 0, imagecolorallocatealpha($scaled, 0, 0, 0, 127));
        imagealphablending($scaled, true);

        imagecopyresampled(
            $scaled, $source,
            0, 0, 0, 0,
            $targetWidth, $targetHeight,
            imagesx($source), imagesy($source)
        );

        if ($item->opacity < 100) {
            // IMG_FILTER_COLORIZE soma alfa a cada pixel (0 opaco, 127
            // invisível). É aproximação — soma em vez de multiplicar —, mas
            // roda em tempo constante; um laço pixel a pixel numa tela de
            // 1080p travaria a tela de edição.
            imagealphablending($scaled, false);
            imagefilter($scaled, IMG_FILTER_COLORIZE, 0, 0, 0, (int) round(127 * (1 - $item->opacity / 100)));
            imagealphablending($scaled, true);
        }

        imagecopy($canvas, $scaled, $targetX, $targetY, 0, 0, $targetWidth, $targetHeight);

        imagedestroy($scaled);
        imagedestroy($source);
    }

    /**
     * Compõe o overlay animado em WebM com canal alpha (VP9 / yuva420p).
     *
     * Devolve null — sem erro — quando não há ffmpeg ou a conversão falha: o
     * PNG já foi gravado e o módulo segue. O problema vai para o log com o
     * comando e a saída do ffmpeg, que é o que permite diagnosticar sem
     * reproduzir.
     */
    private function composeAnimated(Layout $layout, $items, array $dimensions, string $hash): ?string
    {
        $binary = (string) config('services.replay.ffmpeg', 'ffmpeg');
        $durationMs = $this->loopDurationMs($items);
        $duration = round($durationMs / 1000, 3);

        $relativePath = MediaService::OVERLAY_DIR . "/{$layout->id}-{$hash}.webm";
        $absolutePath = MediaService::absolutePath($relativePath);

        @mkdir(dirname($absolutePath), 0775, true);

        $command = [
            $binary, '-y', '-hide_banner', '-loglevel', 'error',
            // Base transparente do tamanho do frame.
            '-f', 'lavfi', '-t', (string) $duration,
            '-i', "color=c=black@0.0:s={$dimensions['width']}x{$dimensions['height']}:r=" . self::FPS . ',format=rgba',
        ];

        $filters = [];
        $previous = '0:v';
        $index = 1;

        foreach ($items as $item) {
            if (! $this->media->disk()->exists($item->image_path)) {
                continue;
            }

            // `-ignore_loop 0` faz o GIF repetir até o -t; sem ele, a logo
            // animada some depois do primeiro ciclo. PNG entra com `-loop 1`
            // pelo mesmo motivo.
            $command[] = $item->animated ? '-ignore_loop' : '-loop';
            $command[] = $item->animated ? '0' : '1';
            $command[] = '-t';
            $command[] = (string) $duration;
            $command[] = '-i';
            $command[] = MediaService::absolutePath($item->image_path);

            $width = max(1, (int) round($dimensions['width'] * $item->width / 100));
            $height = max(1, (int) round($dimensions['height'] * $item->height / 100));
            $x = (int) round($dimensions['width'] * $item->x / 100);
            $y = (int) round($dimensions['height'] * $item->y / 100);
            $alpha = round($item->opacity / 100, 3);

            $filters[] = "[{$index}:v]scale={$width}:{$height},format=rgba,colorchannelmixer=aa={$alpha}[s{$index}]";
            $filters[] = "[{$previous}][s{$index}]overlay={$x}:{$y}:format=auto[b{$index}]";

            $previous = "b{$index}";
            $index++;
        }

        if ($filters === []) {
            return null;
        }

        $command = array_merge($command, [
            '-filter_complex', implode(';', $filters),
            '-map', "[{$previous}]",
            '-c:v', 'libvpx-vp9',
            '-pix_fmt', 'yuva420p',
            '-b:v', '1M',
            '-an',
            '-t', (string) $duration,
            $absolutePath,
        ]);

        try {
            $result = Process::timeout(120)->run($command);
        } catch (\Throwable $e) {
            Log::warning('Replay: ffmpeg indisponível — overlay animado não gerado.', [
                'layout_id' => $layout->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if (! $result->successful() || ! is_file($absolutePath)) {
            Log::warning('Replay: ffmpeg falhou ao compor o overlay animado.', [
                'layout_id' => $layout->id,
                'exit_code' => $result->exitCode(),
                'stderr' => $result->errorOutput(),
            ]);

            @unlink($absolutePath);

            return null;
        }

        return $relativePath;
    }

    /**
     * Duração do laço: o mínimo múltiplo comum dos ciclos dos GIFs, para que
     * todos voltem ao primeiro quadro juntos e o vídeo não tenha um salto
     * visível ao repetir. Limitado por MAX_LOOP_MS — dois GIFs com ciclos
     * primos entre si produziriam um mmc gigante.
     */
    private function loopDurationMs($items): int
    {
        $duration = null;

        foreach ($items as $item) {
            if (! $item->animated || ! $this->media->disk()->exists($item->image_path)) {
                continue;
            }

            $cycle = $this->gifCycleMs($this->media->disk()->get($item->image_path));

            if ($cycle <= 0) {
                continue;
            }

            $duration = $duration === null ? $cycle : $this->lcm($duration, $cycle);

            if ($duration >= self::MAX_LOOP_MS) {
                return self::MAX_LOOP_MS;
            }
        }

        return max(self::MIN_LOOP_MS, min(self::MAX_LOOP_MS, $duration ?? self::MIN_LOOP_MS));
    }

    /**
     * Soma os atrasos dos quadros do GIF, lidos do bloco de controle gráfico
     * (0x21F904 + 1 byte de flags + 2 bytes de atraso em centésimos, little
     * endian).
     *
     * Atraso 0 vira 100ms, que é como todo navegador trata GIF sem atraso
     * declarado — copiar esse comportamento mantém o overlay no mesmo ritmo
     * do que o Marketing viu no preview.
     */
    private function gifCycleMs(string $binary): int
    {
        $total = 0;
        $offset = 0;

        while (($position = strpos($binary, "\x21\xF9\x04", $offset)) !== false) {
            $delay = unpack('v', substr($binary, $position + 4, 2));
            $centiseconds = $delay[1] ?? 0;
            $total += ($centiseconds > 0 ? $centiseconds : 10) * 10;
            $offset = $position + 4;
        }

        return $total;
    }

    private function lcm(int $a, int $b): int
    {
        return (int) ($a / $this->gcd($a, $b) * $b);
    }

    private function gcd(int $a, int $b): int
    {
        return $b === 0 ? $a : $this->gcd($b, $a % $b);
    }
}
