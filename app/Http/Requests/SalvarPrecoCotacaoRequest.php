<?php

namespace App\Http\Requests;

use App\Models\CotacaoPreco;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Salvamento de uma célula da grade.
 *
 * A VALIDAÇÃO PRINCIPAL AQUI NÃO É DE FORMATO, É DE ESCOPO. O item e o
 * fornecedor têm de pertencer AO MAPA DA ROTA — sem isso, trocar um id no
 * corpo da requisição escreveria preço no mapa de outro comprador (IDOR). Por
 * isso as duas regras `exists` carregam o `where` do mapa em vez de só conferir
 * que a linha existe.
 *
 * A autorização (permissão + mapa ainda editável) é da policy, chamada no
 * controller.
 */
class SalvarPrecoCotacaoRequest extends FormRequest
{
    /**
     * O item e o fornecedor pertencem AO MAPA DA ROTA?
     *
     * A checagem é de autorização, não de formato — por isso vive aqui e devolve
     * 403, e não 422. Apontar para a linha de outro mapa não é um dado mal
     * digitado: é uma tentativa de escrever onde não se pode (IDOR).
     *
     * Ids ausentes ou não numéricos passam adiante de propósito: quem responde
     * "o campo é obrigatório" é a validação, com mensagem melhor que um 403 seco.
     */
    public function authorize(): bool
    {
        $mapa = $this->route('mapa');

        if ($mapa === null) {
            return false;
        }

        return $this->pertenceAoMapa($mapa, 'item_id', 'itens')
            && $this->pertenceAoMapa($mapa, 'fornecedor_id', 'fornecedores');
    }

    /**
     * @param  \App\Models\CotacaoMapa  $mapa
     * @param  string  $relacao  nome da relação no model do mapa
     */
    private function pertenceAoMapa($mapa, string $campo, string $relacao): bool
    {
        $id = $this->input($campo);

        if (! is_numeric($id)) {
            return true;
        }

        return $mapa->{$relacao}()->whereKey((int) $id)->exists();
    }

    protected function prepareForValidation(): void
    {
        $valor = $this->input('valor_unitario');

        // A grade é digitada por humano em teclado brasileiro: "1.234,56" e
        // "1234,56" chegam aqui como texto. Normalizar no request evita que
        // cada tela invente a própria conversão.
        if (is_string($valor) && $valor !== '') {
            $limpo = str_replace(['.', ' '], '', $valor);
            $limpo = str_replace(',', '.', $limpo);

            $this->merge(['valor_unitario' => is_numeric($limpo) ? $limpo : $valor]);
        }

        if ($valor === '') {
            $this->merge(['valor_unitario' => null]);
        }
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
                'required',
                'integer',
                Rule::exists('cotacao_mapa_fornecedores', 'id')->where('cotacao_mapa_id', $mapaId),
            ],
            'situacao' => ['required', Rule::in(CotacaoPreco::SITUACOES)],
            // Nulo é válido e obrigatório nas duas situações sem preço: nunca
            // zero para dizer "não tem", porque zero é um preço.
            'valor_unitario' => [
                'nullable',
                'numeric',
                'min:0',
                'max:99999999999',
                Rule::requiredIf(fn () => $this->input('situacao') === CotacaoPreco::SITUACAO_COTADO),
            ],
            'marca' => ['nullable', 'string', 'max:80'],
            'observacao' => ['nullable', 'string', 'max:255'],
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
            'valor_unitario.required' => 'Informe o preço ou mude a situação para "não trabalha" / "sem resposta".',
            'valor_unitario.numeric' => 'O preço precisa ser um número.',
            'valor_unitario.min' => 'O preço não pode ser negativo.',
        ];
    }

    /**
     * Os dados prontos para o `updateOrCreate`, já com a coerência entre
     * situação e valor resolvida: fora de `cotado`, o valor é nulo — e não o
     * número que ficou na tela antes de o comprador mudar o seletor.
     *
     * @return array<string, mixed>
     */
    public function paraGravacao(): array
    {
        $situacao = $this->string('situacao')->toString();

        return [
            'situacao' => $situacao,
            'valor_unitario' => $situacao === CotacaoPreco::SITUACAO_COTADO
                ? (float) $this->input('valor_unitario')
                : null,
            'marca' => $this->input('marca') ?: null,
            'observacao' => $this->input('observacao') ?: null,
        ];
    }
}
