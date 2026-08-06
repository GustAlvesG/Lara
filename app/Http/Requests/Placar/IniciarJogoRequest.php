<?php

namespace App\Http\Requests\Placar;

use Illuminate\Foundation\Http\FormRequest;

class IniciarJogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'operador' => ['nullable', 'string', 'max:255'],
        ];
    }
}
