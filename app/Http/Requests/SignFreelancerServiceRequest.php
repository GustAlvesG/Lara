<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Assinatura do freelancer pelo bot: exige o login que está conduzindo o
 * atendimento e a reconfirmação da senha dele.
 */
class SignFreelancerServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'password' => ['required', 'string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'user_id' => 'usuário logado',
            'password' => 'senha',
        ];
    }
}
