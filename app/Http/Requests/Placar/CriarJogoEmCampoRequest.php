<?php

namespace App\Http\Requests\Placar;

use App\Models\Placar\Modalidade;
use App\Models\Placar\Time;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /placar/jogos — modo avulso.
 * body: { modalidade, time_casa_id, time_fora_id, data_hora?, local?, competicao_id? }
 *
 * A modalidade do jogo precisa bater com a dos dois times — não dá pra
 * expressar isso com uma rule estática, então fica no withValidator.
 */
class CriarJogoEmCampoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'modalidade' => ['required', 'string'],
            'time_casa_id' => ['required', 'integer', 'exists:times,id', 'different:time_fora_id'],
            'time_fora_id' => ['required', 'integer', 'exists:times,id'],
            'data_hora' => ['nullable', 'date'],
            'local' => ['nullable', 'string', 'max:255'],
            'competicao_id' => ['nullable', 'integer', 'exists:competicoes,id'],
        ];
    }

    public function withValidator(ValidatorContract $validator): void
    {
        $validator->after(function (ValidatorContract $validator) {
            $modalidade = Modalidade::resolver($this->input('modalidade'));

            if (!$modalidade) {
                $validator->errors()->add('modalidade', 'Modalidade desconhecida.');
                return;
            }

            foreach (['time_casa_id', 'time_fora_id'] as $campo) {
                $timeId = $this->input($campo);
                $time = $timeId ? Time::find($timeId) : null;

                if ($time && $time->modalidade_id !== $modalidade->id) {
                    $validator->errors()->add($campo, 'Este time não é da modalidade informada.');
                }
            }
        });
    }
}
