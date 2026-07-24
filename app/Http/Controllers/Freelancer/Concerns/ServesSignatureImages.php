<?php

namespace App\Http\Controllers\Freelancer\Concerns;

use App\Models\FreelancerService;
use Illuminate\Support\Facades\Storage;

/**
 * Entrega a imagem da assinatura de um contrato por uma rota, e não por URL
 * direta do disco público.
 *
 * Dois motivos: a assinatura é prova documental ligada ao CPF de uma pessoa e
 * não deve ficar adivinhável por URL; e a entrega deixa de depender do link
 * `public/storage`, que nem sempre existe no ambiente.
 *
 * Quem usa o trait é responsável por proteger a rota — o painel pela sessão
 * web + permissão, o kiosk pela própria sessão de operador.
 */
trait ServesSignatureImages
{
    /** @param  'freelancer'|'coordinator'  $party */
    protected function signatureImageResponse(FreelancerService $service, string $party)
    {
        $path = $party === 'coordinator'
            ? $service->coordinator_signature_path
            : $service->freelancer_signature_path;

        abort_if(!$path, 404);

        $disk = Storage::disk('public');

        abort_if(!$disk->exists($path), 404);

        return $disk->response($path, null, [
            'Content-Type' => 'image/png',
            // Assinatura não muda depois de gravada, mas também não deve ficar
            // em cache compartilhado por ser dado pessoal.
            'Cache-Control' => 'private, max-age=604800',
        ]);
    }
}
