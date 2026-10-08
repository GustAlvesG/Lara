<?php

namespace App\Http\Requests;

use App\Models\SignatureTemplate;
use App\Services\Signature\SignatureFieldTypes;
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
     * As opções de um campo de escolha chegam como texto, uma por linha — é
     * como a pessoa as digita na tela. Viram lista antes de validar.
     */
    protected function prepareForValidation(): void
    {
        $variaveis = collect($this->input('variables', []))
            ->map(function ($variavel) {
                if (is_array($variavel) && is_string($variavel['options'] ?? null)) {
                    $variavel['options'] = array_values(array_unique(array_filter(
                        array_map('trim', preg_split('/\R/u', $variavel['options']) ?: []),
                        fn(string $linha) => $linha !== '',
                    )));
                }

                return $variavel;
            })
            ->all();

        $this->merge(['variables' => $variaveis]);
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
            'variables.*.type' => ['nullable', Rule::in(array_keys(SignatureFieldTypes::LABELS))],
            // Respondido por quem assina, no tablet, em vez de pelo atendente.
            'variables.*.question' => ['nullable', 'string', 'max:200'],
            'variables.*.options' => ['nullable', 'array', 'max:30'],
            'variables.*.options.*' => ['string', 'max:120'],

            // As partes que assinam ("Contratante", "Contratado"). A chave é
            // o que vai no marcador: [[assinatura:contratante]].
            'parties' => ['nullable', 'array', 'max:10'],
            'parties.*.key' => ['required', 'string', 'max:60', 'regex:/^[a-z][a-z0-9_]*$/'],
            'parties.*.label' => ['required', 'string', 'max:120'],

            // Anexos que todo documento deste modelo pede (identidade,
            // comprovante). A chave sai do rótulo — ver SignatureAttachmentService.
            'attachments' => ['nullable', 'array', 'max:20'],
            'attachments.*.label' => ['nullable', 'string', 'max:120'],
            'attachments.*.required' => ['nullable', 'boolean'],

            'requires_photo' => ['nullable', 'boolean'],
            'requires_initials' => ['nullable', 'boolean'],
            'identity_check' => ['required', Rule::in(array_keys(SignatureTemplate::IDENTITY_CHECKS))],

            'retention_months' => ['nullable', 'integer', 'min:1', 'max:1200'],
        ];
    }

    /**
     * Campo de escolha sem o que escolher não é campo: a pessoa chegaria a uma
     * pergunta sem resposta possível.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function ($validator) {
            foreach ((array) $this->input('variables', []) as $i => $variavel) {
                $tipo = $variavel['type'] ?? SignatureFieldTypes::TEXT;

                if (is_string($tipo)
                    && SignatureFieldTypes::hasOptions($tipo)
                    && count((array) ($variavel['options'] ?? [])) < 2) {
                    $validator->errors()->add(
                        "variables.{$i}.options",
                        'O campo "' . ($variavel['label'] ?? $variavel['key'] ?? '') . '" é de escolha: '
                            . 'informe ao menos duas opções, uma por linha.',
                    );
                }
            }
        });
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
