<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Busca de avisos em nome de um funcionário: o aviso tem privacidade, então a
 * busca precisa saber quem pergunta. A IA (Lara) já identifica o funcionário
 * como `portal_{id}` na conversa (ver LaraClient) e repassa esse mesmo valor.
 */
class SearchAvisoRequest extends KeywordSearchRequest
{
    private const USER_PREFIX = 'portal_';

    /**
     * O usuário pode chegar como `user_id` ou `usuario_id`, com ou sem o
     * prefixo `portal_` — o mesmo identificador que a IA recebe do Lara.
     */
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $user = $this->input('user_id', $this->input('usuario_id'));

        if (is_scalar($user)) {
            $user = trim((string) $user);

            if (str_starts_with($user, self::USER_PREFIX)) {
                $user = substr($user, strlen(self::USER_PREFIX));
            }

            $this->merge(['user_id' => $user]);
        }
    }

    protected function extraRules(): array
    {
        return [
            'user_id'         => ['required', 'integer', 'min:1'],
            'include_expired' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'user_id.required' => 'Informe o usuário que pergunta (user_id).',
            'user_id.integer'  => 'O usuário deve ser o id do Lara, com ou sem o prefixo portal_.',
        ]);
    }

    /**
     * Quem pergunta. Usuário apagado (soft delete) ou inexistente não vira
     * "só avisos públicos": é erro, para a integração não responder em nome
     * de alguém que o Lara não reconhece.
     */
    public function asker(): User
    {
        $user = User::find((int) $this->validated()['user_id']);

        if (!$user) {
            throw new HttpResponseException(
                response()->json([
                    'success' => false,
                    'message' => 'Validation errors',
                    'errors'  => ['user_id' => ['Usuário não encontrado.']],
                ], 422)
            );
        }

        return $user;
    }
}
