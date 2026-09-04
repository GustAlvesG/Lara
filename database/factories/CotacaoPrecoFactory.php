<?php

namespace Database\Factories;

use App\Models\CotacaoMapaFornecedor;
use App\Models\CotacaoMapaItem;
use App\Models\CotacaoPreco;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CotacaoPreco>
 */
class CotacaoPrecoFactory extends Factory
{
    protected $model = CotacaoPreco::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cotacao_mapa_item_id' => CotacaoMapaItem::factory(),
            'cotacao_mapa_fornecedor_id' => CotacaoMapaFornecedor::factory(),
            'valor_unitario' => $this->faker->randomFloat(2, 10, 500),
            'situacao' => CotacaoPreco::SITUACAO_COTADO,
        ];
    }

    /**
     * O "NT" da planilha: o fornecedor NÃO vende este item.
     */
    public function naoTrabalha(): static
    {
        return $this->state(fn () => [
            'situacao' => CotacaoPreco::SITUACAO_NAO_TRABALHA,
            'valor_unitario' => null,
        ]);
    }

    /**
     * Célula vazia: foi consultado e não respondeu. Diferente de `naoTrabalha`
     * — só esta conta contra a cobertura do fornecedor.
     */
    public function semResposta(): static
    {
        return $this->state(fn () => [
            'situacao' => CotacaoPreco::SITUACAO_SEM_RESPOSTA,
            'valor_unitario' => null,
        ]);
    }
}
