<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A decisão de compra de um item: de qual fornecedor comprar.
 *
 * As duas regras `exists` carregam o `where` do mapa da rota pelo mesmo motivo
 * de {@see SalvarPrecoCotacaoRequest}: sem isso, um id trocado no corpo da
 * requisição decidiria a compra no mapa de outra pessoa.
 *
 * `fornecedor_id` nulo é válido e significa "desfazer a escolha" — o comprador
 * precisa poder voltar atrás sem apagar o mapa.
 */
class DefinirVencedorCotacaoRequest extends FormRequest
{
    /**
     * Mesma trava de escopo de {@see SalvarPrecoCotacaoRequest}: apontar para a
     * linha de outro mapa é 403, não 422.
     */
    public function authorize(): bool
    {
        $mapa = $this->route('mapa');

        if ($mapa === null) {
            return false;
        }

        foreach (['item_id' => 'itens', 'fornecedor_id' => 'fornecedores'] as $campo => $relacao) {
            $id = $this->input($campo);

            if (is_numeric($id) && ! $mapa->{$relacao}()->whereKey((int) $id)->exists()) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        $mapaId = $this->route('mapa')?->id;

        return [
            'item_id' => [
                'required',
                'integer',
                Rule::exists('cotacao_mapa_itens', 'id')->where('cotacao_mapa_id', $mapaId),
            ],
            'fornecedor_id' => [
                'nullable',
                'integer',
                Rule::exists('cotacao_mapa_fornecedores', 'id')->where('cotacao_mapa_id', $mapaId),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'item_id.exists' => 'Este item não pertence a este mapa.',
            'fornecedor_id.exists' => 'Este fornecedor não é uma coluna deste mapa.',
        ];
    }
}
