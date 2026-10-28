<?php

namespace App\Http\Requests\Concerns;

use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Buscas da API feitas em nome de um funcionário (Lara IA): avisos e guias.
 * A IA identifica o funcionário como `portal_{id}` na conversa (ver
 * LaraClient) e repassa esse mesmo valor em `user_id` (ou `usuario_id`).
 */
trait ResolvesAskingUser
{
    private string $askingUserPrefix = 'portal_';

    /** Chame no prepareForValidation(). */
    protected function normalizeAskingUser(): void
    {
        $user = $this->input('user_id', $this->input('usuario_id'));

        if (is_scalar($user)) {
            $user = trim((string) $user);

            if (str_starts_with($user, $this->askingUserPrefix)) {
                $user = substr($user, strlen($this->askingUserPrefix));
            }

            $this->merge(['user_id' => $user]);
        }
    }

    /** @return array<string, array<int, string>> */
    protected function askingUserRules(): array
    {
        return ['user_id' => ['required', 'integer', 'min:1']];
    }

    /** @return array<string, string> */
    protected function askingUserMessages(): array
    {
        return [
            'user_id.required' => 'Informe o usuário que pergunta (user_id).',
            'user_id.integer'  => 'O usuário deve ser o id do Lara, com ou sem o prefixo portal_.',
        ];
    }

    /**
     * Quem pergunta. Usuário apagado (soft delete) ou inexistente não vira
     * "resposta genérica": é erro, para a integração não responder em nome
     * de alguém que O Lara não reconhece.
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
