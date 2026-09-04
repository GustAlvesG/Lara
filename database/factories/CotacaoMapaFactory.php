<?php

namespace Database\Factories;

use App\Models\CotacaoMapa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CotacaoMapa>
 */
class CotacaoMapaFactory extends Factory
{
    protected $model = CotacaoMapa::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'questor_solicitacao' => $this->faker->unique()->numberBetween(10000, 99999),
            'questor_empresa' => 1,
            'questor_filial' => 1,
            'titulo' => mb_strtoupper($this->faker->sentence(5)),
            'solicitante' => $this->faker->name(),
            'departamento' => 'MANUTENÇÃO',
            'comprador' => $this->faker->name(),
            'data_mapa' => now()->toDateString(),
            'status' => CotacaoMapa::STATUS_RASCUNHO,
            // Sem `User::factory()`: o model User fixa a conexão `mysql` e a
            // suíte roda em SQLite. Quem precisar de autor vincula à mão.
            'user_id' => null,
        ];
    }

    public function emCotacao(): static
    {
        return $this->state(fn () => ['status' => CotacaoMapa::STATUS_EM_COTACAO]);
    }

    public function fechado(): static
    {
        return $this->state(fn () => ['status' => CotacaoMapa::STATUS_FECHADO]);
    }
}
