<?php

namespace App\Http\Requests\Placar;

use App\Services\Placar\JogoEventoLoteService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida só o envelope do lote (é uma lista, cada item é um objeto, no
 * máximo 200) — a correção de cada evento em si (tipo válido, regra de
 * modalidade, uuid, sequência...) é do JogoEventoLoteService, e
 * deliberadamente NÃO é FormRequest: um evento inválido no meio do lote
 * precisa virar "rejeitado" com motivo, não derrubar a requisição inteira.
 */
class EventosLoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'eventos' => ['required', 'array', 'min:1', 'max:' . JogoEventoLoteService::LIMITE_LOTE],
            'eventos.*' => ['array'],
        ];
    }

    public function messages(): array
    {
        return [
            'eventos.max' => 'Máximo de ' . JogoEventoLoteService::LIMITE_LOTE . ' eventos por lote.',
        ];
    }
}
