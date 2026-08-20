<?php

namespace Tests\Unit\Placar;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Mockery;
use Tests\Concerns\MigratesPlacarSchema;
use Tests\TestCase;

/**
 * Regra de acesso ao Placar Clube (setor Esporte, qualquer papel) — Unit,
 * sem banco de dados do Placar: User está preso à conexão mysql (ver
 * docs/models.md), então qualquer teste que exercite `belongsToSectorNamed()`
 * de verdade precisa ser Unit com um mock, nunca Feature contra o SQLite da
 * suíte. Migra só a tabela `permissions` (vazia): Gate::forUser()->allows()
 * passa pelo Gate::before() global do Spatie, que a consulta sempre, mesmo
 * para abilities que não são dele.
 */
class PlacarGatesTest extends TestCase
{
    use MigratesPlacarSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migratePlacarSchema();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function usuario(bool $noSetorEsporte): User
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('belongsToSectorNamed')
            ->andReturnUsing(fn (string $nome) => $noSetorEsporte && $nome === User::SPORT_SECTOR);

        return $user;
    }

    public function test_quem_esta_no_setor_esporte_tem_os_dois_gates(): void
    {
        $user = $this->usuario(true);

        $this->assertTrue(Gate::forUser($user)->allows('manage-placar-cadastro'));
        $this->assertTrue(Gate::forUser($user)->allows('view-placar-scout'));
    }

    public function test_quem_nao_esta_no_setor_esporte_nao_tem_nenhum_gate(): void
    {
        $user = $this->usuario(false);

        $this->assertFalse(Gate::forUser($user)->allows('manage-placar-cadastro'));
        $this->assertFalse(Gate::forUser($user)->allows('view-placar-scout'));
    }

    public function test_estar_em_outro_setor_nao_da_acesso_ao_placar(): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('belongsToSectorNamed')
            ->with(User::SPORT_SECTOR)->andReturn(false);

        $this->assertFalse($user->canAccessPlacar());
    }

    public function test_o_resultado_e_memorizado_na_instancia(): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        // Só espera UMA chamada — se canAccessPlacar() não memorizasse,
        // chamar duas vezes bateria no mock duas vezes e o teste falharia.
        $user->shouldReceive('belongsToSectorNamed')->once()->andReturn(true);

        $this->assertTrue($user->canAccessPlacar());
        $this->assertTrue($user->canAccessPlacar());
    }
}
