<?php

namespace App\Http\Requests\Placar;

use App\Models\Placar\Modalidade;
use App\Models\Placar\Time;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /placar/jogadores — modo avulso.
 * body: { nome, nome_exibicao?, time_id?, equipe_id?, modalidade?, numero?, temporada? }
 *
 * Sem foto, sem data de nascimento, sem documento — nada disso é essencial
 * para o jogador entrar em quadra.
 *
 * O jogador pertence a UMA equipe e UMA modalidade. Com `time_id`, as duas
 * são herdadas do time (o caminho normal em campo: cria o jogador já
 * entrando no elenco). Sem `time_id`, precisam vir explícitas — senão o
 * cadastro nasceria incompleto e o jogador não poderia entrar em time
 * nenhum.
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
            'equipe_id' => ['nullable', 'integer', 'exists:equipes,id'],
            'modalidade' => ['nullable'],
            'numero' => ['nullable', 'string', 'max:10'],
            'temporada' => ['nullable', 'integer'],
        ];
    }

    public function withValidator(ValidatorContract $validator): void
    {
        $validator->after(function (ValidatorContract $validator) {
            if ($this->filled('time_id')) {
                $this->conferirCoerenciaComOTime($validator);

                return;
            }

            if (!$this->filled('equipe_id')) {
                $validator->errors()->add('equipe_id', 'Informe `equipe_id` (ou `time_id`, que já define a equipe).');
            }

            if (blank($this->input('modalidade'))) {
                $validator->errors()->add('modalidade', 'Informe `modalidade` (ou `time_id`, que já define a modalidade).');
            } elseif (!Modalidade::resolver($this->input('modalidade'))) {
                $validator->errors()->add('modalidade', 'Modalidade desconhecida.');
            }
        });
    }

    /**
     * Com `time_id`, equipe e modalidade vêm do time. Se vierem também no
     * corpo e divergirem, é erro de quem chamou — melhor recusar do que
     * escolher um dos dois em silêncio.
     */
    private function conferirCoerenciaComOTime(ValidatorContract $validator): void
    {
        $time = Time::find($this->input('time_id'));

        if (!$time) {
            return;
        }

        if ($this->filled('equipe_id') && (int) $this->input('equipe_id') !== $time->equipe_id) {
            $validator->errors()->add('equipe_id', 'A equipe informada não é a do time em `time_id`.');
        }

        if (filled($this->input('modalidade'))) {
            $modalidade = Modalidade::resolver($this->input('modalidade'));

            if ($modalidade && $modalidade->id !== $time->modalidade_id) {
                $validator->errors()->add('modalidade', 'A modalidade informada não é a do time em `time_id`.');
            }
        }
    }
}
