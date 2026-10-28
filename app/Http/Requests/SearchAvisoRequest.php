<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesAskingUser;

/**
 * Busca de avisos em nome de um funcionário: o aviso tem privacidade, então a
 * busca precisa saber quem pergunta (ver ResolvesAskingUser).
 */
class SearchAvisoRequest extends KeywordSearchRequest
{
    use ResolvesAskingUser;

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();
        $this->normalizeAskingUser();
    }

    protected function extraRules(): array
    {
        return $this->askingUserRules() + [
            'include_expired' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), $this->askingUserMessages());
    }
}
