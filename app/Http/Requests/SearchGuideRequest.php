<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesAskingUser;

/**
 * Busca nos guias do usuário em nome de um funcionário. O `user_id` não
 * filtra (guia não é sigiloso): decide `tem_acesso` e os links de cada seção.
 * `modulo` é o da tela em que a pessoa está (contexto que a Lara recebe do
 * balão) e só dá preferência às seções daquele módulo.
 */
class SearchGuideRequest extends KeywordSearchRequest
{
    use ResolvesAskingUser;

    public const DEFAULT_LIMIT = 6;

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();
        $this->normalizeAskingUser();
    }

    protected function extraRules(): array
    {
        return $this->askingUserRules() + [
            'modulo' => ['nullable', 'string', 'max:80'],
            'limit'  => ['nullable', 'integer', 'min:1', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), $this->askingUserMessages());
    }

    public function modulo(): ?string
    {
        $modulo = trim((string) ($this->validated()['modulo'] ?? ''));

        return $modulo !== '' ? $modulo : null;
    }

    public function limit(): int
    {
        return (int) ($this->validated()['limit'] ?? self::DEFAULT_LIMIT);
    }
}
