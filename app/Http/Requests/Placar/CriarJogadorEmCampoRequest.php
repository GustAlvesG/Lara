<?php

namespace App\Http\Requests\Placar;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /placar/jogadores — modo avulso.
 * body: { nome, nome_exibicao?, time_id?, numero?, temporada? }
 * Sem foto, sem data de nascimento, sem documento — nada disso é essencial
 * para o jogador entrar em quadra.
 */
class CriarJogadorEmCampoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'nome_exibicao' => ['nullable', 'string', 'max:255'],
            'time_id' => ['nullable', 'integer', 'exists:times,id'],
            'numero' => ['nullable', 'string', 'max:10'],
            'temporada' => ['nullable', 'integer'],
        ];
    }
}
