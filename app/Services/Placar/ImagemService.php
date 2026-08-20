<?php

namespace App\Services\Placar;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Upload de logos/fotos do Placar Clube — multipart OU base64, redimensiona
 * e corta em GD puro (sem intervention/image, por decisão explícita: evitar
 * dependência nova). Ponto único de verdade sobre formato aceito, tamanho
 * máximo e as dimensões de saída.
 *
 * O formato de saída é sempre detectado pelos bytes reais da imagem
 * (`getimagesizefromstring`), nunca pelo mimetype declarado pelo cliente —
 * tanto o `Content-Type` do multipart quanto o prefixo `data:image/...` do
 * base64 são só pistas, não prova.
 */
class ImagemService
{
    /**
     * Disco de toda a mídia do Placar — ver a justificativa em
     * config/filesystems.php. Escrita e remoção passam por aqui; a URL
     * pública sai de `url()`, nunca de `Storage::url()`.
     */
    const DISCO = 'placar';

    const MIME_EXTENSOES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    const TAMANHO_LOGO = 512;
    const TAMANHO_FOTO = 512;
    const TAMANHO_MAXIMO_BYTES = 8 * 1024 * 1024;

    /**
     * Lê, valida tamanho/formato e devolve os bytes crus + a extensão real
     * da imagem enviada em `$campo` (multipart) ou `{$campo}_base64`
     * (data URL) — o que vier primeiro.
     *
     * @return array{binario: string, extensao: string}
     */
    public function extrair(Request $request, string $campo = 'arquivo'): array
    {
        $binario = $this->lerBytes($request, $campo);
        $this->validarTamanho($binario);

        return [
            'binario' => $binario,
            'extensao' => $this->detectarExtensao($binario),
        ];
    }

    private function lerBytes(Request $request, string $campo): string
    {
        if ($request->hasFile($campo)) {
            return file_get_contents($request->file($campo)->getRealPath());
        }

        $base64 = $request->input("{$campo}_base64");
        if (blank($base64)) {
            throw new InvalidArgumentException(
                "Envie a imagem em `{$campo}` (multipart) ou `{$campo}_base64` (data URL)."
            );
        }

        if (!preg_match('/^data:image\/[a-zA-Z]+;base64,/', $base64)) {
            throw new InvalidArgumentException('Base64 inválido — use o formato data:image/<tipo>;base64,...');
        }

        $binario = base64_decode(substr($base64, strpos($base64, ',') + 1), true);
        if ($binario === false) {
            throw new InvalidArgumentException('Base64 inválido.');
        }

        return $binario;
    }

    private function validarTamanho(string $binario): void
    {
        if (strlen($binario) > self::TAMANHO_MAXIMO_BYTES) {
            throw new InvalidArgumentException('Imagem maior que 8MB.');
        }
    }

    private function detectarExtensao(string $binario): string
    {
        $info = @getimagesizefromstring($binario);
        if ($info === false) {
            throw new InvalidArgumentException('Arquivo não é uma imagem válida.');
        }

        $mime = $info['mime'] ?? '';
        if (!isset(self::MIME_EXTENSOES[$mime])) {
            throw new InvalidArgumentException('Formato não aceito — envie jpg, jpeg, png ou webp.');
        }

        return self::MIME_EXTENSOES[$mime];
    }

    /** Logo: cabe em 512×512 mantendo a proporção, sem cortar. */
    public function redimensionarLogo(string $binario, string $extensao): string
    {
        return $this->processar($binario, self::TAMANHO_LOGO, crop: false, extensao: $extensao);
    }

    /** Foto de jogador: corte quadrado centralizado, depois 512×512. */
    public function redimensionarFoto(string $binario, string $extensao): string
    {
        return $this->processar($binario, self::TAMANHO_FOTO, crop: true, extensao: $extensao);
    }

    private function processar(string $binario, int $tamanho, bool $crop, string $extensao): string
    {
        $origem = @imagecreatefromstring($binario);
        if ($origem === false) {
            throw new InvalidArgumentException('Não foi possível ler a imagem — arquivo corrompido?');
        }

        imagealphablending($origem, true);
        imagesavealpha($origem, true);

        $larguraOriginal = imagesx($origem);
        $alturaOriginal = imagesy($origem);

        if ($crop) {
            $lado = min($larguraOriginal, $alturaOriginal);
            $origemX = intdiv($larguraOriginal - $lado, 2);
            $origemY = intdiv($alturaOriginal - $lado, 2);
            $larguraOrigem = $alturaOrigem = $lado;
            $larguraDestino = $alturaDestino = $tamanho;
        } else {
            $origemX = $origemY = 0;
            $larguraOrigem = $larguraOriginal;
            $alturaOrigem = $alturaOriginal;
            // Mantém a proporção, sem ultrapassar $tamanho em nenhum lado
            // (nunca aumenta imagem menor que o alvo).
            $escala = min($tamanho / $larguraOriginal, $tamanho / $alturaOriginal, 1);
            $larguraDestino = max(1, (int) round($larguraOriginal * $escala));
            $alturaDestino = max(1, (int) round($alturaOriginal * $escala));
        }

        $destino = imagecreatetruecolor($larguraDestino, $alturaDestino);

        if ($extensao === 'jpg') {
            // JPEG não tem canal alfa — fundo branco em vez de transparência.
            imagefill($destino, 0, 0, imagecolorallocate($destino, 255, 255, 255));
        } else {
            imagealphablending($destino, false);
            imagesavealpha($destino, true);
            imagefill($destino, 0, 0, imagecolorallocatealpha($destino, 0, 0, 0, 127));
            imagealphablending($destino, true);
        }

        imagecopyresampled(
            $destino, $origem,
            0, 0, $origemX, $origemY,
            $larguraDestino, $alturaDestino, $larguraOrigem, $alturaOrigem
        );

        $codificado = $this->codificar($destino, $extensao);

        imagedestroy($origem);
        imagedestroy($destino);

        return $codificado;
    }

    private function codificar($destino, string $extensao): string
    {
        ob_start();
        match ($extensao) {
            'png' => imagepng($destino, null, 6),
            'webp' => imagewebp($destino, null, 85),
            default => imagejpeg($destino, null, 85),
        };

        return ob_get_clean();
    }

    /**
     * URL pública de um caminho de mídia do Placar — ponto único de verdade,
     * usado por Equipe::logoUrl(), Time::logoUrl(), Jogador::fotoUrl() e
     * Jogador::videoUrl().
     *
     * Usa `asset()` (e não `Storage::url()`) porque o disco `public` do
     * Laravel monta a URL a partir de `APP_URL` fixo no .env: com o app
     * servido em qualquer outro host/porta que não o configurado, todo link
     * sairia apontando para o lugar errado. `asset()` resolve a partir da
     * request em curso, então funciona igual em local, homologação e
     * produção sem depender de APP_URL estar certo.
     */
    public static function url(?string $caminho): ?string
    {
        return $caminho ? asset('storage/' . ltrim($caminho, '/')) : null;
    }

    /**
     * Grava em `$caminho`, apagando `$caminhoAnterior` se for diferente —
     * cobre o caso de troca de extensão (era .jpg, virou .png) sem deixar
     * arquivo órfão.
     */
    public function salvar(string $binario, string $caminho, ?string $caminhoAnterior): string
    {
        if ($caminhoAnterior && $caminhoAnterior !== $caminho) {
            Storage::disk(self::DISCO)->delete($caminhoAnterior);
        }

        Storage::disk(self::DISCO)->put($caminho, $binario);

        return $caminho;
    }

    public function remover(?string $caminho): void
    {
        if ($caminho) {
            Storage::disk(self::DISCO)->delete($caminho);
        }
    }
}
