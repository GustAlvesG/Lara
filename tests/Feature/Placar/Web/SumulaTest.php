<?php

namespace Tests\Feature\Placar\Web;

use App\Models\Placar\Equipe;
use App\Models\Placar\Jogador;
use App\Models\Placar\Jogo;
use App\Models\Placar\JogoEvento;
use App\Models\Placar\Modalidade;
use App\Models\Placar\Time;
use Illuminate\Support\Str;
use Tests\Concerns\MigratesPlacarSchema;
use Tests\Concerns\MocksPlacarUser;
use Tests\TestCase;

/**
 * A súmula (tela de scout) precisa renderizar tanto um jogo sem nenhum
 * evento (estado vazio) quanto um com eventos (placar, timeline, totais).
 */
class SumulaTest extends TestCase
{
    use MigratesPlacarSchema;
    use MocksPlacarUser;

    private Time $timeCasa;
    private Time $timeFora;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migratePlacarSchema();

        $futsal = Modalidade::create(['nome' => 'Futsal', 'slug' => 'futsal', 'ativo' => true]);
        $equipeA = Equipe::create(['nome' => 'Equipe A', 'ativo' => true]);
        $equipeB = Equipe::create(['nome' => 'Equipe B', 'ativo' => true]);
        $this->timeCasa = Time::create(['equipe_id' => $equipeA->id, 'modalidade_id' => $futsal->id, 'categoria' => 'Adulto', 'ativo' => true]);
        $this->timeFora = Time::create(['equipe_id' => $equipeB->id, 'modalidade_id' => $futsal->id, 'categoria' => 'Adulto', 'ativo' => true]);
    }

    private function criarJogo(string $status): Jogo
    {
        return Jogo::create([
            'modalidade_id' => $this->timeCasa->modalidade_id,
            'time_casa_id' => $this->timeCasa->id,
            'time_fora_id' => $this->timeFora->id,
            'data_hora' => now(),
            'status' => $status,
            'criado_em_campo' => false,
        ]);
    }

    public function test_jogo_sem_eventos_mostra_estado_vazio(): void
    {
        $jogo = $this->criarJogo(Jogo::STATUS_AGENDADO);

        $this->actingAs($this->usuarioDoSetorEsporte())
            ->get(route('placar.scout.sumula', $jogo))
            ->assertOk()
            ->assertSee('Nenhum evento registrado');
    }

    public function test_jogo_com_eventos_mostra_placar_e_timeline(): void
    {
        $jogo = $this->criarJogo(Jogo::STATUS_AO_VIVO);
        $jogador = Jogador::create(['nome' => 'Artilheiro da Súmula', 'ativo' => true]);

        JogoEvento::create([
            'uuid' => (string) Str::uuid(), 'jogo_id' => $jogo->id, 'sequencia' => 1,
            'tipo' => JogoEvento::TIPO_PONTO, 'time_id' => $this->timeCasa->id, 'jogador_id' => $jogador->id,
            'valor' => 1, 'periodo' => 1, 'ocorrido_em' => now(),
        ]);

        $resposta = $this->actingAs($this->usuarioDoSetorEsporte())
            ->get(route('placar.scout.sumula', $jogo))
            ->assertOk();

        $resposta->assertSee('Artilheiro da Súmula');
        $resposta->assertSee('AO VIVO');
        $resposta->assertDontSee('Nenhum evento registrado');
    }

    public function test_evento_estornado_some_do_placar_mas_continua_na_timeline(): void
    {
        $jogo = $this->criarJogo(Jogo::STATUS_AO_VIVO);
        $jogador = Jogador::create(['nome' => 'Jogador Estornado', 'ativo' => true]);
        $uuidOriginal = (string) Str::uuid();

        JogoEvento::create([
            'uuid' => $uuidOriginal, 'jogo_id' => $jogo->id, 'sequencia' => 1,
            'tipo' => JogoEvento::TIPO_PONTO, 'time_id' => $this->timeCasa->id, 'jogador_id' => $jogador->id,
            'valor' => 1, 'periodo' => 1, 'ocorrido_em' => now(),
        ]);
        JogoEvento::create([
            'uuid' => (string) Str::uuid(), 'jogo_id' => $jogo->id, 'sequencia' => 2,
            'tipo' => JogoEvento::TIPO_ESTORNO, 'ocorrido_em' => now(),
            'payload' => ['evento_uuid' => $uuidOriginal],
        ]);

        $this->actingAs($this->usuarioDoSetorEsporte())
            ->get(route('placar.scout.sumula', $jogo))
            ->assertOk()
            // continua na timeline...
            ->assertSee('Jogador Estornado')
            // ...mas o placar não conta o ponto estornado.
            ->assertSeeInOrder(['0', 'x', '0']);
    }

    public function test_impressao_da_sumula_responde_200(): void
    {
        $jogo = $this->criarJogo(Jogo::STATUS_ENCERRADO);

        $this->actingAs($this->usuarioDoSetorEsporte())
            ->get(route('placar.scout.sumula.print', $jogo))
            ->assertOk();
    }
}
