<?php

namespace App\Services\EmailCampaign;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Imagens enviadas pelo editor do Disparo de E-mails.
 *
 * Ficam em storage/app/email-campaigns/images — fora do `public`, servidas
 * pela rota autenticada `email-campaigns.images.show` só para a prévia. No
 * envio, o CampaignRenderer troca o endereço da rota por `cid:` e o e-mail
 * leva o arquivo embutido. Não pertencem a uma campanha: duplicar campanha
 * ou colar o HTML em outra continua funcionando.
 */
final class CampaignImageStore
{
    public const EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'webp'];

    /** Nome gerado (uuid + extensão): é a única coisa que a rota aceita. */
    public const NAME_PATTERN = '[a-f0-9\-]{36}\.(?:png|jpe?g|gif|webp)';

    public function store(UploadedFile $file): string
    {
        $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension());
        $extension = $extension === 'jpeg' ? 'jpg' : $extension;

        $name = Str::uuid()->toString() . '.' . $extension;

        File::ensureDirectoryExists($this->directory());
        $file->move($this->directory(), $name);

        return $name;
    }

    public function path(string $name): ?string
    {
        if (! preg_match('/^' . self::NAME_PATTERN . '$/', $name)) {
            return null;
        }

        $path = $this->directory() . DIRECTORY_SEPARATOR . $name;

        return is_file($path) ? $path : null;
    }

    public function url(string $name): string
    {
        return route('email-campaigns.images.show', ['file' => $name]);
    }

    public function directory(): string
    {
        return storage_path('app/email-campaigns/images');
    }
}
