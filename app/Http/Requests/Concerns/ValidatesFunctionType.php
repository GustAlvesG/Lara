<?php

namespace App\Http\Requests\Concerns;

use App\Models\FunctionFreelancer;

/**
 * Regras do cadastro de função nas duas modalidades — compartilhadas entre a
 * criação e a edição, para que os dois formulários não divirjam.
 *
 * Cada tipo exige o seu preço e ignora o do outro: freelancer pede o valor do
 * bloco de 15 min; cachê pede as dez faixas, de 2h a 11h. Faixa faltando
 * barraria o lançamento lá na frente, com o coordenador já na tela de
 * solicitação — melhor recusar aqui.
 */
trait ValidatesFunctionType
{
    /** @return array<string, mixed> */
    protected function functionTypeRules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'in:' . implode(',', array_keys(FunctionFreelancer::TYPES))],
            // Só o tipo freelancer cobra por bloco de 15 minutos.
            'price' => ['nullable', 'required_if:type,' . FunctionFreelancer::TYPE_FREELANCER, 'numeric', 'min:0'],
            'cache_rates' => ['array'],
        ];

        foreach (FunctionFreelancer::cacheHourRange() as $hours) {
            $rules["cache_rates.{$hours}"] = [
                'nullable',
                'required_if:type,' . FunctionFreelancer::TYPE_CACHE,
                'numeric',
                'min:0',
            ];
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        $messages = [
            'price.required_if' => 'Informe o valor por bloco de 15 minutos.',
        ];

        foreach (FunctionFreelancer::cacheHourRange() as $hours) {
            $messages["cache_rates.{$hours}.required_if"] = "Informe o valor da faixa de {$hours}h.";
        }

        return $messages;
    }
}
