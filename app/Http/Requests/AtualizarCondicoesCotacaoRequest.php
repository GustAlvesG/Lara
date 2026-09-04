<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Salvamento em lote das condições de TODAS as colunas do mapa.
 *
 * Existe porque cotação se anota de uma vez: o comprador volta do telefone com
 * frete, prazo e pagamento de cinco fornecedores e, com um botão por linha,
 * precisava salvar cinco vezes — e bastava esquecer um clique para o mapa sair
 * com uma condição desatualizada, sem nada na tela avisando.
 *
 * A VALIDAÇÃO PRINCIPAL AQUI NÃO É DE FORMATO, É DE ESCOPO, como no
 * {@see SalvarPrecoCotacaoRequest}: as chaves do array `fornecedores` são ids,
 * vêm do formulário e podem ser trocadas à mão. Uma delas apontando para a
 * coluna do mapa de outro comprador gravaria lá — e é por isso que a conferência
 * é de autorização (403), e não de validação (422).
 *
 * Frete, prazo e pagamento seguem texto livre pelo mesmo motivo da tela: o mapa
 * em uso hoje tem "CONFIRMAR", "3DU" e "Á VISTA", que não existem em tabela
 * nenhuma do Questor.
 */
class AtualizarCondicoesCotacaoRequest extends FormRequest
{
    /**
     * TODOS os ids enviados são colunas DESTE mapa?
     *
     * Uma chave estranha reprova o lote inteiro em vez de ser ignorada em
     * silêncio: salvar 4 de 5 linhas e responder "salvo" seria pior que
     * recusar, porque o comprador não teria como saber qual ficou de fora.
     */
    public function authorize(): bool
    {
        $mapa = $this->route('mapa');

        if ($mapa === null) {
            return false;
        }

        $ids = array_keys((array) $this->input('fornecedores', []));

        // Lote vazio não é tentativa de invasão — a validação responde melhor.
        if ($ids === []) {
            return true;
        }

        foreach ($ids as $id) {
            if (! is_numeric($id)) {
                return false;
            }
        }

        $ids = array_map('intval', $ids);

        return $mapa->fornecedores()->whereKey($ids)->count() === count(array_unique($ids));
    }

    /**
     * Os dois campos em reais chegam como texto digitado em teclado brasileiro
     * ("1.234,56"). A normalização é a mesma do formulário de uma coluna só —
     * daí vir antes das regras, e não dentro delas.
     */
    protected function prepareForValidation(): void
    {
        $fornecedores = (array) $this->input('fornecedores', []);

        foreach ($fornecedores as $id => $dados) {
            if (! is_array($dados)) {
                continue;
            }

            foreach (['valor_frete', 'desconto'] as $campo) {
                $valor = $dados[$campo] ?? null;

                if (is_string($valor) && $valor !== '') {
                    $limpo = str_replace(',', '.', str_replace(['.', ' '], '', $valor));
                    $fornecedores[$id][$campo] = is_numeric($limpo) ? $limpo : $valor;
                }
            }
        }

        $this->merge(['fornecedores' => $fornecedores]);
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'fornecedores' => ['required', 'array', 'min:1'],
            'fornecedores.*.nome' => ['required', 'string', 'max:150'],
            'fornecedores.*.frete' => ['nullable', 'string', 'max:20'],
            'fornecedores.*.prazo_entrega' => ['nullable', 'string', 'max:30'],
            'fornecedores.*.condicao_pagamento' => ['nullable', 'string', 'max:30'],
            'fornecedores.*.valor_frete' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'fornecedores.*.desconto' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fornecedores.required' => 'Não havia nenhuma coluna para salvar.',
            'fornecedores.*.nome.required' => 'O nome do fornecedor é o que vai no cabeçalho da coluna.',
            'fornecedores.*.valor_frete.numeric' => 'O frete em reais precisa ser um número.',
            'fornecedores.*.valor_frete.min' => 'O frete não pode ser negativo.',
            'fornecedores.*.desconto.numeric' => 'O desconto precisa ser um número.',
            'fornecedores.*.desconto.min' => 'O desconto não pode ser negativo.',
        ];
    }

    /**
     * O lote pronto para gravação: `[id => campos]`, com os dois valores em
     * reais já numéricos.
     *
     * Vazio vira ZERO, e não nulo, porque as colunas de frete e desconto têm
     * DEFAULT 0 — e porque apagar o campo é a forma de dizer "não tem frete".
     *
     * @return array<int, array<string, mixed>>
     */
    public function paraGravacao(): array
    {
        $lote = [];

        /** @var array<int, array<string, mixed>> $fornecedores */
        $fornecedores = $this->validated()['fornecedores'] ?? [];

        foreach ($fornecedores as $id => $dados) {
            $lote[(int) $id] = [
                'nome' => trim((string) $dados['nome']),
                'frete' => $dados['frete'] ?? null,
                'prazo_entrega' => $dados['prazo_entrega'] ?? null,
                'condicao_pagamento' => $dados['condicao_pagamento'] ?? null,
                'valor_frete' => (float) ($dados['valor_frete'] ?? 0),
                'desconto' => (float) ($dados['desconto'] ?? 0),
            ];
        }

        return $lote;
    }
}
