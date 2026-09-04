<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Nova coluna de fornecedor no mapa.
 *
 * `questor_cd_entidade` é opcional de propósito: o comprador pode acrescentar à
 * cotação um fornecedor que ainda não existe no cadastro do Questor. Exigir o
 * código faria a cotação esperar o cadastro do ERP, que é o contrário do que
 * este módulo serve para fazer.
 *
 * `prazo_entrega` e `condicao_pagamento` são texto livre e não `in:` — o mapa
 * em uso hoje tem "CONFIRMAR", "3DU" e "Á VISTA", que não existem nas tabelas
 * do Questor. As tabelas entram como autocomplete, não como restrição.
 */
class StoreCotacaoFornecedorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['valor_frete', 'desconto'] as $campo) {
            $valor = $this->input($campo);

            if (is_string($valor) && $valor !== '') {
                $limpo = str_replace(',', '.', str_replace(['.', ' '], '', $valor));
                $this->merge([$campo => is_numeric($limpo) ? $limpo : $valor]);
            }
        }
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:150'],
            'questor_cd_entidade' => ['nullable', 'integer', 'min:1'],
            'cnpj' => ['nullable', 'string', 'max:20'],
            'contato' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:150'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'frete' => ['nullable', 'string', 'max:20'],
            'prazo_entrega' => ['nullable', 'string', 'max:30'],
            'condicao_pagamento' => ['nullable', 'string', 'max:30'],
            'valor_frete' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'desconto' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'validade_proposta' => ['nullable', 'date'],
            'observacoes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nome.required' => 'O nome do fornecedor é o que vai no cabeçalho da coluna.',
            'valor_frete.min' => 'O frete não pode ser negativo.',
            'desconto.min' => 'O desconto não pode ser negativo.',
        ];
    }

    /**
     * Já com os valores numéricos normalizados — vazio vira zero, e não nulo,
     * porque as colunas de frete e desconto têm DEFAULT 0.
     *
     * @return array<string, mixed>
     */
    public function paraGravacao(): array
    {
        $dados = $this->safe()->except(['valor_frete', 'desconto']);

        $dados['valor_frete'] = (float) ($this->input('valor_frete') ?: 0);
        $dados['desconto'] = (float) ($this->input('desconto') ?: 0);

        return $dados;
    }
}
