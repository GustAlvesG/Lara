<?php

namespace App\Services;

use App\Models\Company\OneOffAccess;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Liberação pontual: a porta de exceção da portaria, para quem chega sem
 * cadastro — nem terceirizado, nem Uber, nem freelancer — e não há tempo de
 * fazer o registro formal.
 *
 * Por ser exceção, é estreita: sem empresa, uma entrada só, no dia em que foi
 * criada. Quem precisa voltar amanhã precisa de outra liberação — ou, de
 * preferência, do cadastro formal.
 */
class OneOffAccessService
{
    /**
     * @param  array{cpf: string, name: string, reason: string, image?: ?string}  $data
     *
     * @throws ValidationException
     */
    public function create(array $data, ?int $userId = null): OneOffAccess
    {
        $cpf = preg_replace('/\D/', '', (string) $data['cpf']);

        // Duas liberações abertas para o mesmo CPF no mesmo dia seriam duas
        // entradas — e a regra é uma. Depois de usada, uma nova pode ser
        // criada: é outra autorização, com o próprio motivo.
        $open = OneOffAccess::forCpf($cpf)->onDate(today())->open()->first();

        if ($open) {
            throw ValidationException::withMessages([
                'cpf' => 'Já existe uma liberação pontual disponível para este CPF hoje (criada às '
                    . $open->created_at->format('H:i') . ').',
            ]);
        }

        return OneOffAccess::create([
            'cpf' => $cpf,
            'name' => trim($data['name']),
            'reason' => trim($data['reason']),
            'image' => $this->storeImage($data['image'] ?? null),
            'access_date' => today(),
            'created_by_user' => $userId,
        ]);
    }

    /**
     * Cancela uma liberação que ainda não foi usada. A usada fica como está:
     * é o registro de uma entrada que aconteceu.
     *
     * @throws ValidationException
     */
    public function cancel(OneOffAccess $access, ?int $userId = null): OneOffAccess
    {
        if ($access->used_at !== null) {
            throw ValidationException::withMessages([
                'one_off_access' => 'Esta liberação já foi utilizada e não pode ser cancelada.',
            ]);
        }

        if ($access->canceled_at === null) {
            $access->forceFill([
                'canceled_at' => now(),
                'canceled_by_user' => $userId,
            ])->save();
        }

        return $access;
    }

    /**
     * Queima a entrada. O UPDATE condicional é a trava: dois registros
     * simultâneos (dois porteiros, ou o botão clicado duas vezes) não passam
     * os dois — só um altera a linha.
     */
    public function consume(OneOffAccess $access): bool
    {
        $consumed = OneOffAccess::whereKey($access->id)
            ->whereDate('access_date', today()->toDateString())
            ->open()
            ->update(['used_at' => now()]) === 1;

        if ($consumed) {
            $access->refresh();
        }

        return $consumed;
    }

    /**
     * Troca a foto em data URL pelo nome do arquivo gravado em `public/images`
     * — o mesmo destino das fotos de terceirizados e freelancers.
     *
     * @throws ValidationException
     */
    private function storeImage(?string $dataUrl): ?string
    {
        if (blank($dataUrl)) {
            return null;
        }

        // O tipo é lido dos bytes, não do cabeçalho do data URL: é o arquivo
        // que o navegador vai abrir.
        [, $encoded] = array_pad(explode(',', $dataUrl, 2), 2, '');
        $bytes = base64_decode($encoded, true);
        $info = $bytes !== false ? @getimagesizefromstring($bytes) : false;

        $extension = match ($info['mime'] ?? null) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => null,
        };

        if ($extension === null) {
            throw ValidationException::withMessages([
                'image' => 'A foto enviada não é uma imagem válida. Tire a foto de novo ou importe outro arquivo.',
            ]);
        }

        $name = 'one_off_' . Str::uuid() . '.' . $extension;
        file_put_contents(public_path('images/' . $name), $bytes);

        return $name;
    }
}
