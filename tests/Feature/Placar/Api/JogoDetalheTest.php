<?php

namespace Tests\Feature\Placar\Api;

use App\Models\Placar\ApiCliente;
use App\Models\Placar\Elenco;
use App\Models\Placar\Equipe;
use App\Models\Placar\Jogador;
use App\Models\Placar\Jogo;
use App\Models\Placar\Modalidade;
use App\Models\Placar\Time;
use App\Support\Placar\PlacarAbilities;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MigratesPlacarSchema;
use Tests\TestCase;

/**
 * GET /placar/jogos/{jogo} — payload completo para o Node montar o
 * gameState. logo_url/foto_url sempre absolutas, e um time sem logo própria
 * herda a da equipe.
 */
class JogoDetalheTest extends TestCase
{
    use MigratesPlacarSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migratePlacarSchema();

        $cliente = ApiCliente::create(['nome' => 'node-teste', 'ativo' => true]);
        Sanctum::actingAs($cliente, [PlacarAbilities::OPERAR]);
    }

    public function test_devolve_urls_absolutas_e_o_time_sem_logo_propria_herda_a_da_equipe(): void
    {
        $futsal = Modalidade::create(['nome' => 'Futsal', 'slug' => 'futsal', 'ativo' => true]);

        $equipeCasa = Equipe::create(['nome' => 'Equipe Casa', 'logo_path' => 'placar/equipes/1/logo.png', 'ativo' => true]);
        $equipeFora = Equipe::create(['nome' => 'Equipe Fora', 'logo_path' => 'placar/equipes/2/logo.png', 'ativo' => true]);

        // Time da casa sem logo própria — precisa herdar a da equipe.
        $timeCasa = Time::create(['equipe_id' => $equipeCasa->id, 'modalidade_id' => $futsal->id, 'categoria' => 'Adulto', 'ativo' => true]);
        // Time de fora com logo própria — não deve usar a da equipe.
        $timeFora = Time::create([
            'equipe_id' => $equipeFora->id, 'modalidade_id' => $futsal->id, 'categoria' => 'Adulto',
            'logo_path' => 'placar/times/2/logo.png', 'ativo' => true,
        ]);

        $jogador = Jogador::create(['nome' => 'Fulano', 'foto_path' => 'placar/jogadores/1/foto.jpg', 'ativo' => true]);
        Elenco::create(['time_id' => $timeCasa->id, 'jogador_id' => $jogador->id, 'temporada' => now()->year, 'numero' => '10', 'ativo' => true]);

        $jogo = Jogo::create([
            'modalidade_id' => $futsal->id, 'time_casa_id' => $timeCasa->id, 'time_fora_id' => $timeFora->id,
            'data_hora' => now(), 'status' => Jogo::STATUS_AGENDADO, 'criado_em_campo' => false,
        ]);

        $resposta = $this->getJson("/api/placar/jogos/{$jogo->id}")->assertOk();
        $body = $resposta->json();

        $this->assertStringStartsWith('http', $body['time_casa']['logo_url']);
        $this->assertStringContainsString('placar/equipes/1/logo.png', $body['time_casa']['logo_url']);

        $this->assertStringStartsWith('http', $body['time_fora']['logo_url']);
        $this->assertStringContainsString('placar/times/2/logo.png', $body['time_fora']['logo_url']);
        $this->assertStringNotContainsString('placar/equipes/2/logo.png', $body['time_fora']['logo_url']);

        $this->assertStringStartsWith('http', $body['time_casa']['elenco'][0]['foto_url']);
        $this->assertStringContainsString('placar/jogadores/1/foto.jpg', $body['time_casa']['elenco'][0]['foto_url']);
    }

    public function test_time_sem_logo_nenhuma_devolve_null(): void
    {
        $futsal = Modalidade::create(['nome' => 'Futsal', 'slug' => 'futsal', 'ativo' => true]);
        $equipe = Equipe::create(['nome' => 'Sem Logo', 'ativo' => true]);
        $timeCasa = Time::create(['equipe_id' => $equipe->id, 'modalidade_id' => $futsal->id, 'categoria' => 'Adulto', 'ativo' => true]);
        $timeFora = Time::create(['equipe_id' => $equipe->id, 'modalidade_id' => $futsal->id, 'categoria' => 'Sub-15', 'ativo' => true]);

        $jogo = Jogo::create([
            'modalidade_id' => $futsal->id, 'time_casa_id' => $timeCasa->id, 'time_fora_id' => $timeFora->id,
            'data_hora' => now(), 'status' => Jogo::STATUS_AGENDADO, 'criado_em_campo' => false,
        ]);

        $this->getJson("/api/placar/jogos/{$jogo->id}")
            ->assertOk()
            ->assertJsonPath('time_casa.logo_url', null);
    }
}
