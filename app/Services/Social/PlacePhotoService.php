<?php

namespace App\Services\Social;

use App\Models\Place;
use App\Models\Social\PlacePhoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Galeria de fotos do espaço social — o carrossel do site de locação.
 *
 * As fotos são **arquivos estáticos** em `public/images/galeria/{espaço}/`:
 * o servidor web entrega direto, sem rota do Lara. O navegador de quem
 * visita o site não alcança o Lara; quem busca o arquivo é o servidor do
 * Next.js, que o serve no domínio dele (ver
 * `docs/prompts/NextJS/galeria-espacos-sociais-prompt.md`).
 *
 * Toda foto é reprocessada em GD (sem dependência nova): girada pela
 * orientação da câmera (EXIF), reduzida a no máximo {@see MAX_SIDE} px no
 * lado maior e regravada em JPEG, mais uma miniatura de {@see THUMB_SIDE} px.
 * Foto de celular chega com 5–10 MB; sai com algumas centenas de KB. O
 * reprocessamento também descarta o EXIF (que pode trazer GPS).
 *
 * Só upload: link externo (imgur…) não entra na galeria, para o site não
 * ter de lidar com dois tipos de origem.
 */
class PlacePhotoService
{
    public const MAX_SIDE = 1920;

    public const THUMB_SIDE = 480;

    /** Tamanho máximo do arquivo enviado (KB) — o mesmo que a validação usa. */
    public const MAX_UPLOAD_KB = 15 * 1024;

    /** Acima disso o GD não decodifica com folga de memória. */
    private const MAX_PIXELS = 50_000_000;

    private const QUALITY = 82;

    private const THUMB_QUALITY = 75;

    private const ACCEPTED = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];

    /** Pasta raiz das imagens (`public/images`); os testes apontam para uma temporária. */
    public function root(): string
    {
        return rtrim((string) (config('social_bookings.gallery_root') ?: public_path('images')), '/\\');
    }

    /**
     * Grava as fotos enviadas no fim da galeria. Tudo ou nada: se uma falha, as
     * já processadas neste envio são apagadas.
     *
     * @param  array<int, UploadedFile>  $files
     * @return int quantas foram gravadas
     *
     * @throws ValidationException (chave `photos`)
     */
    public function add(Place $place, array $files, ?int $userId = null): int
    {
        $this->assertSocial($place);

        $files = array_values(array_filter($files, fn ($f) => $f instanceof UploadedFile));
        if ($files === []) {
            $this->fail('Escolha ao menos uma foto.');
        }

        $current = PlacePhoto::where('place_id', $place->id)->count();
        if ($current + count($files) > PlacePhoto::MAX_PER_PLACE) {
            $free = max(0, PlacePhoto::MAX_PER_PLACE - $current);
            $this->fail("A galeria aceita até " . PlacePhoto::MAX_PER_PLACE . " fotos por espaço. Este já tem {$current}" . ($free ? "; envie no máximo {$free}." : '; exclua alguma antes.'));
        }

        $written = [];
        $rows = [];

        try {
            foreach ($files as $index => $file) {
                $rows[] = $this->process($place, $file, $index + 1, count($files), $written);
            }
        } catch (\Throwable $e) {
            $this->unlinkAll($written);
            throw $e;
        }

        try {
            DB::transaction(function () use ($place, $rows, $userId) {
                $position = (int) PlacePhoto::where('place_id', $place->id)->max('position');
                foreach ($rows as $row) {
                    PlacePhoto::create($row + [
                        'place_id' => $place->id,
                        'position' => ++$position,
                        'active' => true,
                        'created_by' => $userId,
                    ]);
                }
            });
        } catch (\Throwable $e) {
            $this->unlinkAll($written);
            throw $e;
        }

        return count($rows);
    }

    public function update(PlacePhoto $photo, ?string $caption, bool $active): void
    {
        $caption = trim((string) $caption);

        $photo->update([
            'caption' => $caption === '' ? null : Str::limit($caption, 120, ''),
            'active' => $active,
        ]);
    }

    /** Troca de lugar com a vizinha de cima (`up`) ou de baixo (`down`). */
    public function move(PlacePhoto $photo, string $direction): void
    {
        DB::transaction(function () use ($photo, $direction) {
            $ids = PlacePhoto::where('place_id', $photo->place_id)->ordered()->lockForUpdate()->pluck('id')->all();
            $at = array_search($photo->id, $ids, true);
            $to = $direction === 'up' ? $at - 1 : $at + 1;

            if ($at === false || ! isset($ids[$to])) {
                return;
            }

            [$ids[$at], $ids[$to]] = [$ids[$to], $ids[$at]];
            $this->renumber($ids);
        });
    }

    /** Apaga a foto e os dois arquivos. */
    public function delete(PlacePhoto $photo): void
    {
        $files = [$this->absolute($photo->file), $this->absolute($photo->thumb)];
        $placeId = $photo->place_id;

        DB::transaction(function () use ($photo, $placeId) {
            $photo->delete();
            $this->renumber(PlacePhoto::where('place_id', $placeId)->ordered()->pluck('id')->all());
        });

        $this->unlinkAll($files);
    }

    /**
     * Usa a foto como capa do espaço (`places.image`). Grava uma cópia própria
     * em `public/images`: a capa não some se a foto sair da galeria depois.
     */
    public function makeCover(PlacePhoto $photo): void
    {
        $source = $this->absolute($photo->file);
        if (! is_file($source)) {
            $this->fail('O arquivo desta foto não está no servidor.');
        }

        $name = Str::random(40) . '.jpg';
        if (! @copy($source, $this->root() . DIRECTORY_SEPARATOR . $name)) {
            $this->fail('Não foi possível copiar a foto para a capa.');
        }

        $photo->place->update(['image' => $name]);
    }

    public function absolute(string $relative): string
    {
        return $this->root() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }

    /**
     * Valida, reprocessa e grava os dois arquivos de uma foto.
     *
     * @param  list<string>  $written  arquivos gravados até aqui (para desfazer)
     * @return array{file: string, thumb: string, width: int, height: int}
     */
    private function process(Place $place, UploadedFile $file, int $number, int $total, array &$written): array
    {
        $label = $total === 1 ? 'A foto' : "A foto {$number} ({$file->getClientOriginalName()})";

        if (! $file->isValid()) {
            $this->fail("{$label} não chegou inteira. Tente de novo (o limite é de " . (self::MAX_UPLOAD_KB / 1024) . ' MB por foto).');
        }
        if ($file->getSize() > self::MAX_UPLOAD_KB * 1024) {
            $this->fail("{$label} passa de " . (self::MAX_UPLOAD_KB / 1024) . ' MB.');
        }

        $path = $file->getRealPath();
        // O tipo vem dos bytes, não da extensão nem do Content-Type do envio.
        $info = @getimagesize($path);
        if ($info === false || ! in_array($info[2], self::ACCEPTED, true)) {
            $this->fail("{$label} não é uma imagem JPG, PNG ou WEBP.");
        }
        if ($info[0] * $info[1] > self::MAX_PIXELS) {
            $this->fail("{$label} tem resolução grande demais (mais de 50 megapixels).");
        }

        $this->ensureMemory();

        $image = @imagecreatefromstring((string) file_get_contents($path));
        if ($image === false) {
            $this->fail("{$label} está corrompida ou não pôde ser lida.");
        }

        if ($info[2] === IMAGETYPE_JPEG) {
            $image = $this->orient($image, $path);
        }

        $dir = PlacePhoto::DIR . '/' . $place->id;
        $absoluteDir = $this->absolute($dir);
        if (! is_dir($absoluteDir) && ! @mkdir($absoluteDir, 0775, true) && ! is_dir($absoluteDir)) {
            imagedestroy($image);
            $this->fail('Não foi possível criar a pasta da galeria no servidor.');
        }

        $base = Str::random(32);
        $relative = "{$dir}/{$base}.jpg";
        $relativeThumb = "{$dir}/{$base}-thumb.jpg";

        try {
            [$width, $height] = $this->saveResized($image, self::MAX_SIDE, $this->absolute($relative), self::QUALITY);
            $written[] = $this->absolute($relative);
            $this->saveResized($image, self::THUMB_SIDE, $this->absolute($relativeThumb), self::THUMB_QUALITY);
            $written[] = $this->absolute($relativeThumb);
        } finally {
            imagedestroy($image);
        }

        return ['file' => $relative, 'thumb' => $relativeThumb, 'width' => $width, 'height' => $height];
    }

    /** @return array{0: int, 1: int} largura e altura gravadas */
    private function saveResized(\GdImage $image, int $maxSide, string $target, int $quality): array
    {
        $w = imagesx($image);
        $h = imagesy($image);
        $scale = min(1, $maxSide / max($w, $h));
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));

        $out = imagecreatetruecolor($nw, $nh);
        // JPEG não tem transparência: PNG com fundo transparente fica branco.
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
        imagecopyresampled($out, $image, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imageinterlace($out, true);

        $ok = imagejpeg($out, $target, $quality);
        imagedestroy($out);

        if (! $ok) {
            $this->fail('Não foi possível gravar a foto no servidor.');
        }

        return [$nw, $nh];
    }

    /** Foto de celular deitada no arquivo e "em pé" só pelo EXIF: gira de verdade. */
    private function orient(\GdImage $image, string $path): \GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $orientation = (int) (@exif_read_data($path)['Orientation'] ?? 1);

        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => null,
        };

        if ($rotated instanceof \GdImage) {
            imagedestroy($image);

            return $rotated;
        }

        return $image;
    }

    /** Uma foto de 12 MP ocupa ~60 MB descompactada no GD; 128 MB não dá folga. */
    private function ensureMemory(): void
    {
        $limit = ini_get('memory_limit');
        if ($limit === '-1') {
            return;
        }

        $bytes = (int) $limit;
        $unit = strtolower(substr((string) $limit, -1));
        $bytes *= match ($unit) { 'g' => 1024 ** 3, 'm' => 1024 ** 2, 'k' => 1024, default => 1 };

        if ($bytes < 512 * 1024 ** 2) {
            @ini_set('memory_limit', '512M');
        }
    }

    /** @param list<int> $ids na ordem final */
    private function renumber(array $ids): void
    {
        foreach (array_values($ids) as $i => $id) {
            PlacePhoto::where('id', $id)->update(['position' => $i + 1]);
        }
    }

    /** @param list<string> $files */
    private function unlinkAll(array $files): void
    {
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    private function assertSocial(Place $place): void
    {
        if (! $place->isSocial()) {
            $this->fail('A galeria de fotos é só para espaços sociais.');
        }
    }

    /** @return never */
    private function fail(string $message): void
    {
        throw ValidationException::withMessages(['photos' => $message]);
    }
}
