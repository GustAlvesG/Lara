<?php

namespace App\Http\Requests\Placar;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /placar/equipes — modo avulso. Validação mínima de propósito: é o
 * operador criando uma equipe às pressas antes de um jogo não planejado.
 */
class CriarEquipeEmCampoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'nome_curto' => ['nullable', 'string', 'max:255'],
            'cidade' => ['nullable', 'string', 'max:255'],
        ];
    }
}
