<?php

namespace App\Http\Requests;

use App\Models\SignatureTemplate;
use Illuminate\Validation\Rule;

/**
 * Envio de um documento PRONTO, em PDF, para assinatura.
 *
 * Herda os signatários (e a normalização do CPF) do envio por modelo, e troca
 * o modelo pelo que este caminho tem no lugar dele: o arquivo e as regras da
 * assinatura, que num documento de modelo viriam do próprio modelo.
 */
class StoreUploadedSignatureDocumentRequest extends StoreSignatureDocumentRequest
{
    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $regras = parent::rules();

        unset($regras['signature_template_id'], $regras['data']);

        return array_merge($regras, [
            'title' => ['required', 'string', 'max:200'],
            'file' => ['required', 'file', 'mimes:pdf', 'max:20480'],

            'identity_check' => ['required', Rule::in(array_keys(SignatureTemplate::IDENTITY_CHECKS))],
            'requires_photo' => ['nullable', 'boolean'],
            'requires_initials' => ['nullable', 'boolean'],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'file.required' => 'Escolha o PDF do documento.',
            'file.mimes' => 'O documento pronto precisa estar em PDF. Do Word, use Salvar como → PDF.',
            'file.max' => 'O PDF passa de 20 MB.',
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return array_merge(parent::attributes(), [
            'file' => 'arquivo',
            'identity_check' => 'conferência de identidade',
        ]);
    }
}
