<?php

namespace App\Http\Controllers\Concerns;

use App\Exceptions\PasswordConfirmationException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Reconfirmação de senha para ações feitas pela integração externa (bot do
 * Telegram). Como a API não mantém sessão, o usuário que conduz o atendimento
 * é identificado pelo id e reautenticado pela senha a cada ação sensível.
 */
trait ConfirmsUserPassword
{
    /**
     * @throws PasswordConfirmationException
     */
    protected function confirmUserPassword(?int $userId, ?string $password): User
    {
        if (!$userId || !$password) {
            throw new PasswordConfirmationException('Informe o usuário logado e a senha para confirmar a ação.');
        }

        $user = User::find($userId);

        if (!$user || !Hash::check($password, $user->password)) {
            throw new PasswordConfirmationException('Senha inválida.');
        }

        return $user;
    }
}
