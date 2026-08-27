<?php

namespace App\Http\Requests\Placar;

use App\Models\Placar\Modalidade;
use App\Models\Placar\Time;
use App\Services\Placar\CategoriaService;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /placar/jogos — modo avulso.
 * body: { modalidade, time_casa_id, time_fora_id, data_hora?, local?, competicao_id? }
 *
 * Duas regras de cruzamento que nenhuma rule estática expressa, por isso
 * ficam no withValidator: a modalidade do jogo precisa bater com a dos dois
 * times, e os dois times precisam ser da mesma categoria — Sub-15 não joga
 * contra Adulto.
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

            $times = [];

            foreach (['time_casa_id', 'time_fora_id'] as $campo) {
                $timeId = $this->input($campo);
                $time = $timeId ? Time::find($timeId) : null;
                $times[$campo] = $time;

                if ($time && $time->modalidade_id !== $modalidade->id) {
                    $validator->errors()->add($campo, 'Este time não é da modalidade informada.');
                }
            }

            [$casa, $fora] = [$times['time_casa_id'], $times['time_fora_id']];

            // Compara pela chave normalizada, não pela string crua: dados
            // antigos podem ter "Sub 15" de um lado e "Sub-15" do outro, e
            // são a mesma categoria — bloquear isso seria falso positivo.
            if ($casa && $fora && CategoriaService::chave($casa->categoria) !== CategoriaService::chave($fora->categoria)) {
                $validator->errors()->add(
                    'time_fora_id',
                    "Os dois times precisam ser da mesma categoria: {$casa->categoria} x {$fora->categoria}.",
                );
            }
        });
    }
}
