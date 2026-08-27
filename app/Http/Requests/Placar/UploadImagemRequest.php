<?php

namespace App\Http\Requests\Placar;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação de primeira camada (existe/tamanho/mimetype do multipart) para
 * os uploads de logo/foto. A segunda camada — formato real dos bytes,
 * inclusive do base64 — é do ImagemService, que não confia em Content-Type
 * nem no prefixo `data:image/...` declarado pelo cliente.
 */
class UploadImagemRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Já passou por auth:sanctum + abilities:placar:operar na rota.
        return true;
    }

    public function rules(): array
    {
        return [
            'arquivo' => ['required_without:arquivo_base64', 'file', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'arquivo_base64' => ['required_without:arquivo', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'arquivo.required_without' => 'Envie a imagem em `arquivo` (multipart) ou `arquivo_base64` (data URL).',
            'arquivo_base64.required_without' => 'Envie a imagem em `arquivo` (multipart) ou `arquivo_base64` (data URL).',
        ];
    }
}
