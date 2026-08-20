<?php

namespace App\Http\Requests\Placar;

use App\Models\Placar\Modalidade;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /placar/times — modo avulso.
 * body: { equipe_id?, equipe_nome?, modalidade, categoria? }
 * Um dos dois (equipe_id OU equipe_nome) precisa vir — sem pré-cadastro
 * obrigatório: se vier só o nome, o controller cria a equipe junto.
 */
class CriarTimeEmCampoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'equipe_id' => ['nullable', 'integer', 'exists:equipes,id'],
            'equipe_nome' => ['required_without:equipe_id', 'string', 'max:255'],
            'modalidade' => ['required', 'string'],
            'categoria' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(ValidatorContract $validator): void
    {
        $validator->after(function (ValidatorContract $validator) {
            if (filled($this->input('modalidade')) && !Modalidade::resolver($this->input('modalidade'))) {
                $validator->errors()->add('modalidade', 'Modalidade desconhecida.');
            }
        });
    }
}
