<?php

namespace App\Http\Requests;

use App\Models\FreelancerDirector;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Cadastro da diretoria: nome, e-mail que recebe os códigos do lote e a imagem
 * da assinatura aplicada aos contratos da redação 2.
 *
 * A imagem só é obrigatória quando ainda não há nenhuma — trocar o e-mail não
 * obriga a reenviar a assinatura.
 */
class UpdateFreelancerDirectorRequest extends FormRequest
{
    /** Tamanho máximo da imagem, em KB. Uma assinatura em PNG tem dezenas de KB. */
    public const MAX_KB = 1024;

    public function authorize(): bool
    {
        return $this->user()?->can('manage-freelancer-director') ?? false;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $temAssinatura = FreelancerDirector::current()?->hasSignature() ?? false;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'signature' => [
                $temAssinatura ? 'nullable' : 'required',
                'file',
                'mimes:png',
                'max:' . self::MAX_KB,
                // Limite generoso: a imagem é reduzida para 74px de altura no
                // documento, e um arquivo gigante só pesaria em cada impressão.
                'dimensions:min_width=100,min_height=30,max_width=3000,max_height=1500',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nome do diretor',
            'email' => 'e-mail do diretor',
            'signature' => 'imagem da assinatura',
        ];
    }

    public function messages(): array
    {
        return [
            'signature.required' => 'Envie a imagem da assinatura do diretor (PNG).',
            'signature.mimes' => 'A assinatura precisa ser um arquivo PNG.',
        ];
    }
}
