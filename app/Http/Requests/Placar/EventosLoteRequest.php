<?php

namespace App\Http\Requests\Placar;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida só o envelope do lote (é uma lista, cada item é um objeto) — sem
 * limite de tamanho, por decisão explícita (o enunciado original sugeria até
 * 200, mas o Node pode precisar mandar lotes maiores, ex.: reenvio de fila
 * offline acumulada). A correção de cada evento em si (tipo válido, regra de
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
            'eventos' => ['required', 'array', 'min:1'],
            'eventos.*' => ['array'],
        ];
    }
}
