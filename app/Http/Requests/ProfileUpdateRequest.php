<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Support\UserContactFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * O próprio usuário atualiza o perfil.
 *
 * CPF (09/11/2026): a pessoa cadastra o dela SÓ quando ainda não tem — depois
 * disso o campo some do formulário e o que chegar é descartado. O CPF sai
 * impresso nos documentos que ela assina; corrigir é na tela de Usuários.
 * Telefone e e-mail ela muda à vontade.
 */
class ProfileUpdateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (filled($this->user()->cpf)) {
            $this->request->remove('cpf');
        }

        UserContactFields::normalize($this);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\Rule|array|string>
     */
    public function rules(): array
    {
        $regras = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($this->user()->id)],
            'matricula' => ['nullable', 'string', 'max:5', Rule::unique(User::class, 'matricula')->ignore($this->user()->id)],
            'phone' => UserContactFields::phoneRules(),
        ];

        if (blank($this->user()->cpf)) {
            $regras['cpf'] = UserContactFields::cpfRules($this->user());
        }

        return $regras;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['cpf' => 'CPF', 'phone' => 'telefone'];
    }
}
