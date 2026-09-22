<?php

namespace App\Http\Requests;

use App\Models\SignatureSigner;
use App\Support\Cpf;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Criação e correção de um documento pelo atendente.
 *
 * O CPF é normalizado ANTES de validar: ele chega com máscara do formulário e
 * sem máscara da busca de associado, e uma regra de tamanho sobre o texto cru
 * recusaria justamente o caminho mais comum.
 */
class StoreSignatureDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $signers = collect($this->input('signers', []))
            ->map(function ($signer) {
                $signer['cpf'] = Cpf::digits($signer['cpf'] ?? '');

                return $signer;
            })
            ->all();

        $this->merge(['signers' => $signers]);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'signature_template_id' => ['required', 'integer', 'exists:signature_templates,id'],
            'title' => ['nullable', 'string', 'max:200'],
            'location' => ['nullable', 'string', 'max:120'],

            // Valores das variáveis do modelo. A obrigatoriedade de cada uma é
            // do MODELO, e é conferida no congelamento — aqui o rascunho pode
            // ficar incompleto de propósito, para o atendente salvar e voltar.
            'data' => ['nullable', 'array'],

            'signers' => ['required', 'array', 'min:1', 'max:5'],
            'signers.*.name' => ['required', 'string', 'max:150'],
            'signers.*.cpf' => ['required', 'digits:11'],
            'signers.*.member_id' => ['nullable', 'integer'],
            'signers.*.email' => ['nullable', 'email', 'max:150'],
            'signers.*.phone' => ['nullable', 'string', 'max:30'],
            'signers.*.role' => ['nullable', Rule::in(array_keys(SignatureSigner::ROLE_LABELS))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'signers.required' => 'Informe quem vai assinar o documento.',
            'signers.*.cpf.digits' => 'O CPF do signatário deve ter 11 dígitos.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'signature_template_id' => 'modelo',
            'title' => 'título',
            'signers.*.name' => 'nome do signatário',
            'signers.*.cpf' => 'CPF do signatário',
        ];
    }
}
