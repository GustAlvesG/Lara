<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Item avulso acrescentado ao mapa — o que não veio na solicitação.
 *
 * `questor_cd_material` é opcional: o comprador pode cotar algo que nem está no
 * cadastro de materiais. Quando ele vem, o drill-down do item ganha histórico;
 * quando não vem, a linha funciona igual, sem histórico — a mesma regra do item
 * de texto livre que chega pela própria solicitação.
 */
class StoreCotacaoItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $qtd = $this->input('quantidade');

        if (is_string($qtd) && $qtd !== '') {
            $limpo = str_replace(',', '.', str_replace(['.', ' '], '', $qtd));
            $this->merge(['quantidade' => is_numeric($limpo) ? $limpo : $qtd]);
        }
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'descricao' => ['required', 'string', 'max:255'],
            'unidade' => ['nullable', 'string', 'max:10'],
            // Maior que zero, não "mínimo zero": item de quantidade zero não é
            // item, e zero no divisor arruinaria o preço unitário do rodapé.
            'quantidade' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'questor_cd_material' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'descricao.required' => 'Descreva o item a cotar.',
            'quantidade.gt' => 'A quantidade precisa ser maior que zero.',
        ];
    }
}
