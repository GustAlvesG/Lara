<?php

namespace App\Http\Requests\Placar;

use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;

class AtualizarEscalacaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'time_id' => ['required', 'integer'],
            'jogadores' => ['required', 'array', 'min:1'],
            'jogadores.*.jogador_id' => ['required', 'integer', 'exists:jogadores,id'],
            'jogadores.*.numero' => ['required', 'string', 'max:10'],
            'jogadores.*.titular' => ['nullable', 'boolean'],
            'jogadores.*.capitao' => ['nullable', 'boolean'],
        ];
    }

    /** time_id precisa ser um dos dois times deste jogo — não dá para validar isso com uma rule estática. */
    public function withValidator(ValidatorContract $validator): void
    {
        $validator->after(function (ValidatorContract $validator) {
            $jogo = $this->route('jogo');
            $timeId = (int) $this->input('time_id');

            if ($jogo && !in_array($timeId, [$jogo->time_casa_id, $jogo->time_fora_id], true)) {
                $validator->errors()->add('time_id', 'Este time não faz parte deste jogo.');
            }
        });
    }
}
