<?php

namespace App\Http\Requests;

use App\Models\SignatureTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Criação e revisão de um modelo de documento.
 *
 * Serve às duas operações porque são a mesma coisa: revisar um modelo não
 * altera a linha em uso, cria a versão seguinte com estes mesmos campos.
 */
class StoreSignatureTemplateRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],

            // O corpo é saneado no controller antes de gravar; a validação
            // aqui é de tamanho, não de conteúdo.
            'body_html' => ['required', 'string', 'max:200000'],

            'signature_placeholder' => ['nullable', 'string', 'max:60'],

            // As variáveis que a tela do atendente vai pedir.
            'variables' => ['nullable', 'array', 'max:40'],
            'variables.*.key' => ['required', 'string', 'max:60', 'regex:/^[a-z][a-z0-9_]*$/'],
            'variables.*.label' => ['required', 'string', 'max:120'],
            'variables.*.required' => ['nullable', 'boolean'],

            'requires_photo' => ['nullable', 'boolean'],
            'identity_check' => ['required', Rule::in(array_keys(SignatureTemplate::IDENTITY_CHECKS))],

            'retention_months' => ['nullable', 'integer', 'min:1', 'max:1200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'variables.*.key.regex' => 'A chave da variável deve começar por letra minúscula e conter '
                . 'apenas letras minúsculas, números e underline (ex.: nome_do_espaco).',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nome',
            'body_html' => 'texto do documento',
            'identity_check' => 'conferência de identidade',
            'retention_months' => 'prazo de guarda',
        ];
    }
}
