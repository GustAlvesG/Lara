<?php

namespace App\Http\Requests;

use App\Services\MultiClubes\MemberMailingFilter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filtros da lista de sócios do MultiClubes (Disparo de E-mails): contar e
 * importar usam as mesmas regras. A permissão é conferida na rota.
 */
class MemberMailingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipos' => ['nullable', 'array', 'max:200'],
            'tipos.*' => ['integer', 'min:1'],
            'situacao_titulo' => ['nullable', 'array'],
            'situacao_titulo.*' => ['integer', Rule::in(array_keys(MemberMailingFilter::TITLE_STATUSES))],
            'situacao_socio' => ['nullable', 'array'],
            'situacao_socio.*' => ['integer', Rule::in(array_keys(MemberMailingFilter::MEMBER_STATUSES))],
            'idade_min' => ['nullable', 'integer', 'min:0', 'max:130'],
            'idade_max' => ['nullable', 'integer', 'min:0', 'max:130'],
            'sexo' => ['nullable', Rule::in(array_map('strval', array_keys(MemberMailingFilter::SEXES)))],
            'vinculo' => ['required', Rule::in(array_keys(MemberMailingFilter::LINKS))],
            'mode' => ['sometimes', 'in:append,replace'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator) {
                $min = $this->input('idade_min');
                $max = $this->input('idade_max');

                if (is_numeric($min) && is_numeric($max) && (int) $max < (int) $min) {
                    $validator->errors()->add('idade_max', 'A idade máxima não pode ser menor que a mínima.');
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'idade_min' => 'idade mínima',
            'idade_max' => 'idade máxima',
            'situacao_titulo.*' => 'situação do título',
            'situacao_socio.*' => 'situação do sócio',
            'vinculo' => 'titular ou dependente',
        ];
    }

    public function filter(): MemberMailingFilter
    {
        return MemberMailingFilter::fromInput($this->validated());
    }
}
