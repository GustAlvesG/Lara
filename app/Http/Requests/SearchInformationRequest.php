<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;

class SearchInformationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A palavra-chave pode chegar como `q`, `keyword` ou `palavra_chave` —
     * a integração externa não é a mesma em todos os consumidores.
     */
    protected function prepareForValidation(): void
    {
        $keyword = $this->input('q', $this->input('keyword', $this->input('palavra_chave')));

        if (is_scalar($keyword)) {
            $this->merge(['q' => trim((string) $keyword)]);
        }
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q'            => ['required', 'string', 'min:2', 'max:100'],
            'all_versions' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'q.required' => 'Informe a palavra-chave da busca (q).',
            'q.min'      => 'A palavra-chave precisa ter ao menos 2 caracteres.',
        ];
    }

    public function keyword(): string
    {
        return (string) $this->validated()['q'];
    }

    /**
     * Reduz a frase a palavras-chave: tokeniza, descarta stopwords
     * (config/search.php) e palavras de 1 caractere. Se sobrar nada — a
     * frase inteira era feita de stopwords — cai de volta para a frase
     * original, para não zerar a busca por excesso de filtro.
     *
     * @return string[]
     */
    public function keywords(): array
    {
        $raw = $this->keyword();
        $stopwords = config('search.stopwords', []);

        preg_match_all('/[\p{L}\p{N}]+/u', $raw, $matches);

        $terms = [];
        foreach ($matches[0] as $word) {
            if (mb_strlen($word) < 2) {
                continue;
            }

            $normalized = mb_strtolower(Str::ascii($word));
            if (in_array($normalized, $stopwords, true)) {
                continue;
            }

            $terms[] = $word;
        }

        $terms = array_values(array_unique($terms));

        return $terms !== [] ? $terms : [$raw];
    }

    /**
     * Sem "Accept: application/json" o Laravel responderia com redirect no
     * lugar do 422, e o consumidor da API veria um HTML.
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors'  => $validator->errors(),
            ], 422)
        );
    }
}
