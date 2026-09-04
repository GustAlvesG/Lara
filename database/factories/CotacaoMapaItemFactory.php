<?php

namespace Database\Factories;

use App\Models\CotacaoMapa;
use App\Models\CotacaoMapaItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CotacaoMapaItem>
 */
class CotacaoMapaItemFactory extends Factory
{
    protected $model = CotacaoMapaItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cotacao_mapa_id' => CotacaoMapa::factory(),
            'questor_cd_item' => $this->faker->numberBetween(1, 40),
            'questor_cd_material' => $this->faker->numberBetween(1000, 9999),
            'descricao' => mb_strtoupper($this->faker->words(4, true)),
            'unidade' => 'UN',
            'quantidade' => $this->faker->numberBetween(1, 20),
            'ordem' => $this->faker->numberBetween(1, 40),
            'origem' => CotacaoMapaItem::ORIGEM_SOLICITACAO,
        ];
    }

    /**
     * O item digitado como texto livre na solicitação: sem CD_MATERIAL e, por
     * consequência, sem histórico de compra por código. É o estado que a
     * armadilha nº 1 do módulo descreve — e o que os testes precisam exercitar.
     */
    public function semCadastro(): static
    {
        return $this->state(fn () => [
            'questor_cd_material' => null,
            'ult_compra_data' => null,
            'ult_compra_fornecedor_id' => null,
            'ult_compra_fornecedor_nome' => null,
            'ult_compra_valor' => null,
            'ult_compra_nf' => null,
        ]);
    }

    public function comUltimaCompra(float $valor, ?string $fornecedor = null): static
    {
        return $this->state(fn () => [
            'ult_compra_valor' => $valor,
            'ult_compra_data' => now()->subMonths(3)->toDateString(),
            'ult_compra_fornecedor_nome' => $fornecedor ?? 'FORNECEDOR ANTERIOR',
            'ult_compra_nf' => (string) $this->faker->numberBetween(1000, 99999),
        ]);
    }

    public function avulso(): static
    {
        return $this->state(fn () => [
            'origem' => CotacaoMapaItem::ORIGEM_AVULSO,
            'questor_cd_item' => null,
        ]);
    }
}
