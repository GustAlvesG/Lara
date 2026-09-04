<?php

namespace Database\Factories;

use App\Models\CotacaoMapa;
use App\Models\CotacaoMapaFornecedor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CotacaoMapaFornecedor>
 */
class CotacaoMapaFornecedorFactory extends Factory
{
    protected $model = CotacaoMapaFornecedor::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cotacao_mapa_id' => CotacaoMapa::factory(),
            'questor_cd_entidade' => $this->faker->numberBetween(100, 9999),
            'nome' => mb_strtoupper($this->faker->company()),
            'frete' => 'CIF',
            'prazo_entrega' => '3DU',
            'condicao_pagamento' => 'Á VISTA',
            'valor_frete' => 0,
            'desconto' => 0,
            'ordem' => $this->faker->numberBetween(1, 10),
        ];
    }

    /**
     * Fornecedor que não existe no cadastro do Questor — o caso que o módulo
     * precisa aceitar para o comprador poder cotar com quem quiser.
     */
    public function foraDoQuestor(): static
    {
        return $this->state(fn () => ['questor_cd_entidade' => null]);
    }

    public function comFrete(float $valor): static
    {
        return $this->state(fn () => ['valor_frete' => $valor]);
    }
}
