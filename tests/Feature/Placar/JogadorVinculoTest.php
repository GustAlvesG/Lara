<?php

namespace Tests\Feature\Placar;

use App\Models\Placar\ApiCliente;
use App\Models\Placar\Elenco;
use App\Models\Placar\Equipe;
use App\Models\Placar\Jogador;
use App\Models\Placar\Modalidade;
use App\Models\Placar\Time;
use App\Support\Placar\PlacarAbilities;
use Database\Seeders\ModalidadeSeeder;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MigratesPlacarSchema;
use Tests\Concerns\MocksPlacarUser;
use Tests\TestCase;

/**
 * Jogador pertence a UMA equipe e UMA modalidade. Ele pode estar em vários
 * times daquela equipe (Sub-15 e Adulto, por exemplo), mas nunca num time
 * de outra equipe nem de outra modalidade.
 */
class JogadorVinculoTest extends TestCase
{
    use MigratesPlacarSchema;
    use MocksPlacarUser;

    private Equipe $equipe;
    private Equipe $outraEquipe;
    private Modalidade $futsal;
    private Modalidade $basquete;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migratePlacarSchema();
        (new ModalidadeSeeder())->run();

        $this->futsal = Modalidade::where('slug', 'futsal')->first();
        $this->basquete = Modalidade::where('slug', 'basquete')->first();
        $this->equipe = Equipe::create(['nome' => 'Clube dos Funcionários', 'ativo' => true]);
        $this->outraEquipe = Equipe::create(['nome' => 'Vila Nova', 'ativo' => true]);
    }

    private function time(Equipe $equipe, Modalidade $modalidade, string $categoria): Time
    {
        return Time::create([
            'equipe_id' => $equipe->id, 'modalidade_id' => $modalidade->id,
            'categoria' => $categoria, 'ativo' => true,
        ]);
    }

    private function jogador(): Jogador
    {
        return Jogador::create([
            'equipe_id' => $this->equipe->id, 'modalidade_id' => $this->futsal->id,
            'nome' => 'Carlos Souza', 'ativo' => true,
        ]);
    }

    public function test_pode_jogar_por_varios_times_da_mesma_equipe_e_modalidade(): void
    {
        $jogador = $this->jogador();

        $adulto = $this->time($this->equipe, $this->futsal, 'Adulto');
        $sub15 = $this->time($this->equipe, $this->futsal, 'Sub-15');

        // É exatamente o caso que a regra precisa PERMITIR.
        $this->assertTrue($jogador->podeJogarPor($adulto));
        $this->assertTrue($jogador->podeJogarPor($sub15));
    }

    public function test_nao_pode_jogar_por_time_de_outra_equipe(): void
    {
        $jogador = $this->jogador();
        $timeDeOutraEquipe = $this->time($this->outraEquipe, $this->futsal, 'Adulto');

        $this->assertFalse($jogador->podeJogarPor($timeDeOutraEquipe));
    }

    public function test_nao_pode_jogar_por_time_de_outra_modalidade(): void
    {
        $jogador = $this->jogador();
        $timeDeBasquete = $this->time($this->equipe, $this->basquete, 'Adulto');

        $this->assertFalse($jogador->podeJogarPor($timeDeBasquete));
    }

    public function test_a_tela_do_time_recusa_adicionar_jogador_de_outra_equipe(): void
    {
        $jogador = $this->jogador();
        $timeDeOutraEquipe = $this->time($this->outraEquipe, $this->futsal, 'Adulto');

        $this->actingAs($this->usuarioDoSetorEsporte())
            ->post(route('placar.times.elenco.store', $timeDeOutraEquipe), [
                'jogadores' => [$jogador->id => ['selecionado' => '1']],
            ])
            ->assertRedirect();

        $this->assertSame(0, Elenco::count());
    }

    public function test_a_tela_do_time_aceita_jogador_da_mesma_equipe_e_modalidade(): void
    {
        $jogador = $this->jogador();
        $time = $this->time($this->equipe, $this->futsal, 'Adulto');

        $this->actingAs($this->usuarioDoSetorEsporte())
            ->post(route('placar.times.elenco.store', $time), [
                'jogadores' => [$jogador->id => ['selecionado' => '1', 'numero' => '10']],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('elencos', [
            'time_id' => $time->id, 'jogador_id' => $jogador->id, 'numero' => '10',
        ]);
    }

    /**
     * A lista de candidatos ao elenco não pode oferecer quem a regra vai
     * recusar depois — o erro apareceria só no clique.
     */
    public function test_a_ficha_do_time_so_oferece_jogadores_elegiveis(): void
    {
        $doTime = $this->jogador();
        Jogador::create([
            'equipe_id' => $this->outraEquipe->id, 'modalidade_id' => $this->futsal->id,
            'nome' => 'Jogador de Outra Equipe', 'ativo' => true,
        ]);
        Jogador::create([
            'equipe_id' => $this->equipe->id, 'modalidade_id' => $this->basquete->id,
            'nome' => 'Jogador de Outra Modalidade', 'ativo' => true,
        ]);

        $time = $this->time($this->equipe, $this->futsal, 'Adulto');

        $this->actingAs($this->usuarioDoSetorEsporte())
            ->get(route('placar.times.show', $time))
            ->assertOk()
            ->assertSee($doTime->nome)
            ->assertDontSee('Jogador de Outra Equipe')
            ->assertDontSee('Jogador de Outra Modalidade');
    }

    public function test_cadastro_web_exige_equipe_e_modalidade(): void
    {
        $this->actingAs($this->usuarioDoSetorEsporte())
            ->post(route('placar.jogadores.store'), ['nome' => 'Sem Vínculo'])
            ->assertSessionHasErrors(['equipe_id', 'modalidade_id']);

        $this->assertSame(0, Jogador::count());
    }

    /**
     * Trocar a equipe de quem já está em elenco deixaria vínculos inválidos
     * para trás — o time é da equipe antiga.
     */
    public function test_nao_troca_a_equipe_de_jogador_que_ja_esta_em_elenco(): void
    {
        $jogador = $this->jogador();
        $time = $this->time($this->equipe, $this->futsal, 'Adulto');
        Elenco::create([
            'time_id' => $time->id, 'jogador_id' => $jogador->id,
            'temporada' => now()->year, 'ativo' => true,
        ]);

        $this->actingAs($this->usuarioDoSetorEsporte())
            ->put(route('placar.jogadores.update', $jogador), [
                'nome' => $jogador->nome,
                'equipe_id' => $this->outraEquipe->id,
                'modalidade_id' => $this->futsal->id,
            ])
            ->assertSessionHasErrors('equipe_id');

        $this->assertSame($this->equipe->id, $jogador->fresh()->equipe_id);
    }

    public function test_api_herda_equipe_e_modalidade_do_time_informado(): void
    {
        Sanctum::actingAs(ApiCliente::create(['nome' => 'node', 'ativo' => true]), [PlacarAbilities::OPERAR]);

        $time = $this->time($this->equipe, $this->futsal, 'Adulto');

        $id = $this->postJson('/api/placar/jogadores', [
            'nome' => 'Criado em Campo', 'time_id' => $time->id, 'numero' => '7',
        ])->assertCreated()->json('id');

        $jogador = Jogador::find($id);
        $this->assertSame($this->equipe->id, $jogador->equipe_id);
        $this->assertSame($this->futsal->id, $jogador->modalidade_id);
    }

    public function test_api_exige_equipe_e_modalidade_quando_nao_ha_time(): void
    {
        Sanctum::actingAs(ApiCliente::create(['nome' => 'node', 'ativo' => true]), [PlacarAbilities::OPERAR]);

        $this->postJson('/api/placar/jogadores', ['nome' => 'Sem Time'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['equipe_id', 'modalidade']);
    }

    public function test_api_recusa_equipe_que_nao_e_a_do_time_informado(): void
    {
        Sanctum::actingAs(ApiCliente::create(['nome' => 'node', 'ativo' => true]), [PlacarAbilities::OPERAR]);

        $time = $this->time($this->equipe, $this->futsal, 'Adulto');

        $this->postJson('/api/placar/jogadores', [
            'nome' => 'Incoerente', 'time_id' => $time->id, 'equipe_id' => $this->outraEquipe->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('equipe_id');
    }
}
