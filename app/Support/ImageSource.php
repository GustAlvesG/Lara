<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * Imagem de grupo e de espaço: arquivo enviado ou link web (imgur, postimg…).
 *
 * O campo guarda uma de duas coisas — e quem lê usa `url()` para não ter de
 * saber qual:
 *
 * - **nome de arquivo** em `public/images` (o upload de sempre);
 * - **URL completa** `https://…` de uma imagem hospedada fora.
 *
 * O link precisa ser da **imagem** (o que o navegador abre como figura), não
 * da página que a mostra. Como as pessoas copiam a página, as duas mais
 * comuns são convertidas aqui: `imgur.com/<id>` vira `i.imgur.com/<id>.jpg`;
 * a página do postimg é lida (só esse domínio) para achar a imagem dela.
 * Qualquer outro endereço precisa terminar em extensão de imagem.
 */
final class ImageSource
{
    private const DIR = 'images';

    private const EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    private const IMGUR_PAGES = ['imgur.com', 'www.imgur.com', 'm.imgur.com'];

    private const POSTIMG_PAGES = ['postimg.cc', 'www.postimg.cc', 'postimages.org', 'www.postimages.org'];

    /** O tamanho da coluna (`string`). */
    private const MAX_LENGTH = 255;

    /**
     * Valor a gravar no campo `$field`: o arquivo enviado em `$field`, senão o
     * link em `{$field}_url`, senão o que já estava (`$current`).
     *
     * @throws ValidationException link que não é de imagem (erro em `{$field}_url`)
     */
    public static function resolve(Request $request, string $field, ?string $current = null): ?string
    {
        $file = $request->file($field);

        if ($file instanceof UploadedFile && $file->isValid()) {
            return self::store($file, $field);
        }

        $link = trim((string) $request->input($field . '_url', ''));

        return $link !== '' ? self::normalizeLink($link, $field . '_url') : $current;
    }

    /** URL para exibir: o link como está, ou o arquivo em `public/images`. */
    public static function url(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return self::isRemote($value) ? $value : asset(self::DIR . '/' . $value);
    }

    public static function isRemote(?string $value): bool
    {
        return is_string($value) && preg_match('#^https?://#i', $value) === 1;
    }

    /**
     * Link direto da imagem a partir do que a pessoa colou.
     *
     * @throws ValidationException
     */
    public static function normalizeLink(string $link, string $errorKey = 'image_url'): string
    {
        $parts = parse_url($link);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower($parts['host'] ?? '');
        $path = $parts['path'] ?? '';

        if ($host === '' || ! in_array($scheme, ['http', 'https'], true)) {
            self::fail($errorKey, 'Informe o endereço completo da imagem, começando com https://.');
        }
        // O site de locação é https: imagem em http é bloqueada pelo navegador.
        if ($scheme !== 'https') {
            self::fail($errorKey, 'O link da imagem precisa começar com https:// (em http o site não mostra).');
        }

        if (in_array($host, self::IMGUR_PAGES, true)) {
            $link = self::imgurDirect($path, $errorKey);
        } elseif (in_array($host, self::POSTIMG_PAGES, true)) {
            $link = self::postimgDirect($link, $errorKey);
        } elseif (! self::hasImageExtension($path)) {
            self::fail($errorKey, 'Use o link direto da imagem (o endereço termina em .jpg, .png, .webp ou .gif). No imgur e no postimg, use "copiar link da imagem" / "link direto".');
        }

        if (mb_strlen($link) > self::MAX_LENGTH) {
            self::fail($errorKey, 'O link da imagem é longo demais (máximo de ' . self::MAX_LENGTH . ' caracteres).');
        }

        return $link;
    }

    private static function store(UploadedFile $file, string $field): string
    {
        $mime = (string) $file->getMimeType();
        $extension = strtolower((string) $file->guessExtension());

        if (! str_starts_with($mime, 'image/') || ! in_array($extension, self::EXTENSIONS, true)) {
            self::fail($field, 'O arquivo enviado não é uma imagem (use JPG, PNG, WEBP ou GIF).');
        }

        // hashName, e não time(): duas imagens no mesmo envio (vertical e
        // horizontal) caíam no mesmo segundo e uma sobrescrevia a outra.
        $name = $file->hashName();
        $file->move(public_path(self::DIR), $name);

        return $name;
    }

    /** `imgur.com/<id>` → `i.imgur.com/<id>.jpg`; álbum e galeria não têm uma imagem só. */
    private static function imgurDirect(string $path, string $errorKey): string
    {
        if (preg_match('#^/([A-Za-z0-9]{5,10})(\.[a-z]{3,4})?/?$#', $path, $m) !== 1) {
            self::fail($errorKey, 'Esse link do imgur é de álbum ou galeria. Abra a imagem e use "Copiar link da imagem" (i.imgur.com/…).');
        }

        return 'https://i.imgur.com/' . $m[1] . '.jpg';
    }

    /**
     * A página do postimg não leva o nome do arquivo no endereço; a imagem
     * está no `og:image` dela. Só este domínio é lido — link de outro lugar
     * não faz o Lara buscar nada.
     */
    private static function postimgDirect(string $link, string $errorKey): string
    {
        try {
            $html = Http::timeout(8)->accept('text/html')->get($link)->throw()->body();
        } catch (\Throwable $e) {
            self::fail($errorKey, 'Não foi possível abrir a página do postimg. Use o "link direto" da imagem (i.postimg.cc/…).');
        }

        if (preg_match('#<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']#i', $html, $m) !== 1
            && preg_match('#<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image["\']#i', $html, $m) !== 1) {
            self::fail($errorKey, 'A página do postimg não traz a imagem. Use o "link direto" (i.postimg.cc/…).');
        }

        $direct = html_entity_decode($m[1]);

        if (! str_starts_with(strtolower($direct), 'https://i.postimg.cc/')) {
            self::fail($errorKey, 'A página do postimg não traz a imagem. Use o "link direto" (i.postimg.cc/…).');
        }

        return $direct;
    }

    private static function hasImageExtension(string $path): bool
    {
        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::EXTENSIONS, true);
    }

    /** @return never */
    private static function fail(string $key, string $message): void
    {
        throw ValidationException::withMessages([$key => $message]);
    }
}
