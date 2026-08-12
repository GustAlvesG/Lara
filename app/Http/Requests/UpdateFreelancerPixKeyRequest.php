<?php

namespace App\Http\Requests;

use App\Models\Freelancer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Correção da chave PIX pelo próprio freelancer, no tablet, na conferência que
 * antecede a assinatura.
 *
 * O TIPO da chave é obrigatório e não é deduzido do que foi digitado: 11
 * dígitos tanto são um CPF quanto um celular com DDD, e errar essa leitura
 * manda o Pix para outro domicílio bancário. Com o tipo à mão, a validação e a
 * normalização ficam sem ambiguidade — as duas moram no model, para valerem
 * também no painel e na API.
 */
class UpdateFreelancerPixKeyRequest extends FormRequest
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
            'pix_key_type' => ['required', 'string', 'in:' . implode(',', Freelancer::PIX_KEY_INPUT_TYPES)],
            'pix_key' => ['required', 'string', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $error = Freelancer::pixKeyError(
                    (string) $this->input('pix_key_type'),
                    (string) $this->input('pix_key'),
                );

                if ($error !== null) {
                    $validator->errors()->add('pix_key', $error);
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'pix_key' => 'chave PIX',
            'pix_key_type' => 'tipo da chave',
        ];
    }
}
