<?php

namespace App\Services;

use App\Models\FreelancerDirector;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Cadastro da diretoria, operado pela gerência.
 *
 * Toda alteração cria um registro novo — nunca edita o anterior. Ver o
 * docblock de `FreelancerDirector`: os contratos aprovados apontam para o
 * registro que os assinou, e ele não pode mudar depois.
 */
class FreelancerDirectorService
{
    /**
     * Grava o cadastro vigente. Sem imagem nova, repete a do registro anterior:
     * trocar só o e-mail não obriga a reenviar a assinatura. O arquivo antigo
     * nunca é apagado — documentos já aprovados continuam apontando para ele.
     *
     * @throws InvalidArgumentException  quando não há imagem nem nova nem anterior
     */
    public function register(string $name, string $email, ?UploadedFile $signature, ?User $actor = null): FreelancerDirector
    {
        $previous = FreelancerDirector::current();

        $path = $signature
            ? $signature->storeAs(FreelancerDirector::DIRECTORY, Str::uuid() . '.png', FreelancerDirector::DISK)
            : $previous?->signature_path;

        if (blank($path)) {
            throw new InvalidArgumentException('Envie a imagem da assinatura do diretor (PNG).');
        }

        $director = FreelancerDirector::create([
            'name' => trim($name),
            'email' => mb_strtolower(trim($email)),
            'signature_path' => $path,
            'created_by' => $actor?->id,
        ]);

        // Quem trocou o destinatário dos códigos, e quando, é a primeira
        // pergunta se um lote for aprovado por quem não devia.
        Log::info('Cadastro da diretoria dos freelancers alterado', [
            'freelancer_director_id' => $director->id,
            'anterior_id' => $previous?->id,
            'email_anterior' => $previous?->email,
            'email' => $director->email,
            'assinatura_trocada' => $signature !== null,
            'alterado_por' => $actor?->id,
        ]);

        return $director;
    }
}
