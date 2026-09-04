<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Geração do mapa a partir de uma Solicitação de Compra.
 *
 * A existência da SC NÃO é validada aqui: ela vive no Questor, noutro banco, e
 * uma regra `exists` não alcança. Quem responde "solicitação 34334 não
 * encontrada" é o serviço de importação, com a mensagem que o comprador
 * precisa ler.
 */
class ImportarMapaCotacaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'solicitacao' => ['required', 'integer', 'min:1'],
            'titulo' => ['nullable', 'string', 'max:255'],
            'comprador' => ['nullable', 'string', 'max:100'],
            // Fornecedores que o comprador marcou para virar coluna. Vazio é
            // válido: o mapa pode nascer sem coluna nenhuma e ganhá-las depois.
            'fornecedores' => ['nullable', 'array', 'max:' . (int) config('questor.cotacao.max_fornecedores', 10)],
            'fornecedores.*' => ['integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'solicitacao.required' => 'Informe o número da solicitação de compra.',
            'solicitacao.integer' => 'O número da solicitação é só dígitos.',
            'fornecedores.max' => 'São :max colunas de fornecedor no máximo — é o que cabe na grade e no papel.',
        ];
    }
}
