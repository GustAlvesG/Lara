<?php

namespace Tests\Feature\Placar\Api;

use App\Models\Placar\ApiCliente;
use App\Models\Placar\Equipe;
use App\Models\Placar\Jogo;
use App\Models\Placar\Modalidade;
use App\Models\Placar\Time;
use App\Services\Placar\CategoriaService;
use App\Support\Placar\PlacarAbilities;
use Database\Seeders\ModalidadeSeeder;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MigratesPlacarSchema;
use Tests\TestCase;

/**
 * Categoria de time: grafia unificada no cadastro, e partida só entre
 * times da mesma categoria.
 *
 * O problema original: "Sub 15" e "Sub-15" são a mesma categoria para quem
 * cadastra, mas duas strings para o UNIQUE (equipe, modalidade, categoria)
 * — cada variação criava um time duplicado da mesma equipe.
 */
class CategoriaTest extends TestCase
{
    use MigratesPlacarSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migratePlacarSchema();
        (new ModalidadeSeeder())->run();

        $cliente = ApiCliente::create(['nome' => 'node-teste', 'ativo' => true]);
        Sanctum::actingAs($cliente, [PlacarAbilities::OPERAR]);
    }

    public function test_variacoes_de_grafia_caem_na_categoria_ja_cadastrada(): void
    {
        // Primeiro cadastro define a grafia: "Sub-15".
        $primeiro = $this->postJson('/api/placar/times', [
            'equipe_nome' => 'Equipe da Base', 'modalidade' => 'futsal', 'categoria' => 'Sub-15',
        ])->assertCreated()->json('id');

        // As três variações precisam cair no MESMO time, não criar novos.
        foreach (['Sub 15', 'sub15', 'SUB-15'] as $variacao) {
            $id = $this->postJson('/api/placar/times', [
                'equipe_nome' => 'Equipe da Base', 'modalidade' => 'futsal', 'categoria' => $variacao,
            ])->assertOk()->json('id');

            $this->assertSame($primeiro, $id, "\"{$variacao}\" deveria cair no time já cadastrado");
        }

        $this->assertSame(1, Time::count());
        $this->assertSame('Sub-15', Time::first()->categoria);
    }

    public function test_categoria_nova_recebe_grafia_canonica(): void
    {
        $this->postJson('/api/placar/times', [
            'equipe_nome' => 'Equipe Nova', 'modalidade' => 'futsal', 'categoria' => 'sub 17',
        ])->assertCreated();

        $this->assertSame('Sub-17', Time::first()->categoria);
    }

    public function test_sem_categoria_usa_o_padrao(): void
    {
        $this->postJson('/api/placar/times', [
            'equipe_nome' => 'Equipe Sem Categoria', 'modalidade' => 'futsal',
        ])->assertCreated();

        $this->assertSame(Time::CATEGORIA_PADRAO, Time::first()->categoria);
    }

    public function test_a_chave_de_comparacao_ignora_caixa_acento_e_separador(): void
    {
        $this->assertSame(CategoriaService::chave('Sub-15'), CategoriaService::chave('sub 15'));
        $this->assertSame(CategoriaService::chave('Sub-15'), CategoriaService::chave('SUB15'));
        // Categorias realmente diferentes continuam diferentes.
        $this->assertNotSame(CategoriaService::chave('Sub-15'), CategoriaService::chave('Sub-17'));
        $this->assertNotSame(CategoriaService::chave('Adulto'), CategoriaService::chave('Master'));
    }

    public function test_recusa_jogo_entre_times_de_categorias_diferentes(): void
    {
        $adulto = $this->criarTime('Equipe A', 'Adulto');
        $sub15 = $this->criarTime('Equipe B', 'Sub-15');

        $this->postJson('/api/placar/jogos', [
            'modalidade' => 'futsal', 'time_casa_id' => $adulto, 'time_fora_id' => $sub15,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('time_fora_id');

        $this->assertSame(0, Jogo::count());
    }

    public function test_aceita_jogo_entre_times_da_mesma_categoria(): void
    {
        $casa = $this->criarTime('Equipe A', 'Sub-15');
        $fora = $this->criarTime('Equipe B', 'Sub-15');

        $this->postJson('/api/placar/jogos', [
            'modalidade' => 'futsal', 'time_casa_id' => $casa, 'time_fora_id' => $fora,
        ])->assertCreated();

        $this->assertSame(1, Jogo::count());
    }

    /**
     * Dados antigos podem ter gravado grafias diferentes para a mesma
     * categoria antes da unificação — isso não pode barrar a criação do
     * jogo, senão a regra vira falso positivo em cima do legado.
     */
    public function test_grafias_diferentes_da_mesma_categoria_nao_bloqueiam_o_jogo(): void
    {
        $futsal = Modalidade::where('slug', 'futsal')->first();
        $equipeA = Equipe::create(['nome' => 'Legado A', 'ativo' => true]);
        $equipeB = Equipe::create(['nome' => 'Legado B', 'ativo' => true]);

        // Gravados direto, como estariam no banco antes da normalização.
        $casa = Time::create(['equipe_id' => $equipeA->id, 'modalidade_id' => $futsal->id, 'categoria' => 'Sub 15', 'ativo' => true]);
        $fora = Time::create(['equipe_id' => $equipeB->id, 'modalidade_id' => $futsal->id, 'categoria' => 'Sub-15', 'ativo' => true]);

        $this->postJson('/api/placar/jogos', [
            'modalidade' => 'futsal', 'time_casa_id' => $casa->id, 'time_fora_id' => $fora->id,
        ])->assertCreated();
    }

    private function criarTime(string $equipe, string $categoria): int
    {
        return $this->postJson('/api/placar/times', [
            'equipe_nome' => $equipe, 'modalidade' => 'futsal', 'categoria' => $categoria,
        ])->assertCreated()->json('id');
    }
}
