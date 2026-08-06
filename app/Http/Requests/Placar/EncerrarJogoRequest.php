<?php

namespace App\Http\Requests\Placar;

use Illuminate\Foundation\Http\FormRequest;

class EncerrarJogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'placar_casa' => ['required', 'integer', 'min:0'],
            'placar_fora' => ['required', 'integer', 'min:0'],
            'sets_casa' => ['nullable', 'integer', 'min:0'],
            'sets_fora' => ['nullable', 'integer', 'min:0'],
            'periodos_jogados' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
