<?php

namespace Tests\Feature\Placar;

use App\Models\Placar\Elenco;
use App\Models\Placar\Equipe;
use App\Models\Placar\Jogador;
use App\Models\Placar\Jogo;
use App\Models\Placar\JogoEvento;
use App\Models\Placar\Modalidade;
use App\Models\Placar\Time;
use App\Services\Placar\ScoutService;
use Illuminate\Support\Str;
use Tests\Concerns\MigratesPlacarSchema;
use Tests\Concerns\MocksPlacarUser;
use Tests\TestCase;

/**
 * A súmula impressa traz as parciais abertas: no papel não há aba para
 * clicar e ver o 3º quarter. Por parcial: pontos, faltas, tempos técnicos,
 * substituições e quem produziu de cada lado.
 */
class ResumoPorPeriodoTest extends TestCase
{
    use MigratesPlacarSchema;
    use MocksPlacarUser;

    private Time $timeCasa;
    private Time $timeFora;
    private Jogador $ala;
    private Jogador $pivo;
    private Jogador $visitante;
    private Jogo $jogo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migratePlacarSchema();

        $basquete = Modalidade::create(['nome' => 'Basquete', 'slug' => 'basquete', 'ativo' => true]);
        $equipeA = Equipe::create(['nome' => 'Equipe da Casa', 'ativo' => true]);
        $equipeB = Equipe::create(['nome' => 'Equipe Visitante', 'ativo' => true]);

        $this->timeCasa = Time::create(['equipe_id' => $equipeA->id, 'modalidade_id' => $basquete->id, 'categoria' => 'Adulto', 'ativo' => true]);
        $this->timeFora = Time::create(['equipe_id' => $equipeB->id, 'modalidade_id' => $basquete->id, 'categoria' => 'Adulto', 'ativo' => true]);

        $this->ala = $this->jogador($this->timeCasa, 'Ala da Casa', '7');
        $this->pivo = $this->jogador($this->timeCasa, 'Pivô da Casa', '5');
        $this->visitante = $this->jogador($this->timeFora, 'Armador Visitante', '9');

        $this->jogo = Jogo::create([
            'modalidade_id' => $basquete->id,
            'time_casa_id' => $this->timeCasa->id, 'time_fora_id' => $this->timeFora->id,
            'data_hora' => now(), 'status' => Jogo::STATUS_ENCERRADO, 'criado_em_campo' => false,
        ]);
    }

    private function jogador(Time $time, string $nome, string $numero): Jogador
    {
        $jogador = Jogador::create([
            'equipe_id' => $time->equipe_id, 'modalidade_id' => $time->modalidade_id,
            'nome' => $nome, 'ativo' => true,
        ]);

        Elenco::create([
            'time_id' => $time->id, 'jogador_id' => $jogador->id,
            'temporada' => now()->year, 'numero' => $numero, 'ativo' => true,
        ]);

        return $jogador;
    }

    private function lance(string $tipo, int $periodo, Time $time, ?Jogador $jogador = null, ?int $valor = null): JogoEvento
    {
        return JogoEvento::create([
            'uuid' => (string) Str::uuid(),
            'jogo_id' => $this->jogo->id,
            'sequencia' => JogoEvento::where('jogo_id', $this->jogo->id)->max('sequencia') + 1,
            'tipo' => $tipo, 'time_id' => $time->id, 'jogador_id' => $jogador?->id,
            'valor' => $valor, 'periodo' => $periodo,
            'cronometro_ms' => 60_000, 'ocorrido_em' => now(),
        ]);
    }

    /** 1º quarter: casa 5 (3+2), fora 2. 2º: casa 2, fora 3. */
    private function partidaEmDoisQuarters(): void
    {
        $this->lance(JogoEvento::TIPO_PONTO, 1, $this->timeCasa, $this->ala, 3);
        $this->lance(JogoEvento::TIPO_PONTO, 1, $this->timeCasa, $this->pivo, 2);
        $this->lance(JogoEvento::TIPO_FALTA, 1, $this->timeCasa, $this->pivo);
        $this->lance(JogoEvento::TIPO_TIMEOUT, 1, $this->timeFora);
        $this->lance(JogoEvento::TIPO_PONTO, 1, $this->timeFora, $this->visitante, 2);

        $this->lance(JogoEvento::TIPO_PONTO, 2, $this->timeCasa, $this->ala, 2);
        $this->lance(JogoEvento::TIPO_PONTO, 2, $this->timeFora, $this->visitante, 3);
    }

    private function sumula(?int $timeId = null, ?int $periodo = null): array
    {
        return app(ScoutService::class)->sumula($this->jogo->fresh(), $timeId, $periodo);
    }

    public function test_traz_uma_entrada_por_parcial_com_os_numeros_dos_dois_times(): void
    {
        $this->partidaEmDoisQuarters();

        $resumo = $this->sumula()['resumo_por_periodo'];

        $this->assertCount(2, $resumo);
        $this->assertSame([1, 2], array_column($resumo, 'periodo'));

        // 1º quarter: 5 x 2, uma falta e um tempo do visitante.
        $this->assertSame(5, $resumo[0]['time_casa']['pontos']);
        $this->assertSame(2, $resumo[0]['time_fora']['pontos']);
        $this->assertSame(1, $resumo[0]['time_casa']['faltas']);
        $this->assertSame(1, $resumo[0]['time_fora']['timeouts']);
        $this->assertSame(0, $resumo[0]['time_casa']['timeouts']);

        $this->assertSame(2, $resumo[1]['time_casa']['pontos']);
        $this->assertSame(3, $resumo[1]['time_fora']['pontos']);
    }

    /** É o que a soma do jogo esconde: quem produziu em cada parcial. */
    public function test_lista_quem_produziu_em_cada_parcial(): void
    {
        $this->partidaEmDoisQuarters();

        $resumo = $this->sumula()['resumo_por_periodo'];

        $primeiro = collect($resumo[0]['time_casa']['jogadores'])->keyBy('jogador_id');
        $this->assertSame(3, $primeiro[$this->ala->id]['pontos']);
        $this->assertSame(2, $primeiro[$this->pivo->id]['pontos']);
        $this->assertSame(1, $primeiro[$this->pivo->id]['faltas']);
        $this->assertSame('7', $primeiro[$this->ala->id]['numero']);

        // No 2º quarter só o ala pontuou pela casa.
        $this->assertCount(1, $resumo[1]['time_casa']['jogadores']);
    }

    public function test_lance_estornado_nao_entra_no_resumo(): void
    {
        $cesta = $this->lance(JogoEvento::TIPO_PONTO, 1, $this->timeCasa, $this->ala, 3);
        JogoEvento::create([
            'uuid' => (string) Str::uuid(), 'jogo_id' => $this->jogo->id, 'sequencia' => 99,
            'tipo' => JogoEvento::TIPO_ESTORNO, 'ocorrido_em' => now(),
            'payload' => ['evento_uuid' => $cesta->uuid],
        ]);

        $resumo = $this->sumula()['resumo_por_periodo'];

        $this->assertSame(0, $resumo[0]['time_casa']['pontos']);
        $this->assertSame([], $resumo[0]['time_casa']['jogadores']);
    }

    /** Com filtro de parcial, o resumo é só o dela. */
    public function test_o_filtro_de_parcial_recorta_o_resumo(): void
    {
        $this->partidaEmDoisQuarters();

        $resumo = $this->sumula(periodo: 2)['resumo_por_periodo'];

        $this->assertCount(1, $resumo);
        $this->assertSame(2, $resumo[0]['periodo']);
    }

    /**
     * No recorte por time, o adversário mantém o placar da parcial (é o
     * resultado) mas não a lista de jogadores — é a súmula do outro time.
     */
    public function test_no_recorte_por_time_o_adversario_fica_sem_a_lista(): void
    {
        $this->partidaEmDoisQuarters();

        $resumo = $this->sumula($this->timeCasa->id)['resumo_por_periodo'];

        $this->assertNotEmpty($resumo[0]['time_casa']['jogadores']);
        $this->assertSame([], $resumo[0]['time_fora']['jogadores']);
        $this->assertSame(2, $resumo[0]['time_fora']['pontos']);
    }

    public function test_a_impressao_mostra_o_resumo_por_parcial(): void
    {
        $this->partidaEmDoisQuarters();

        $this->actingAs($this->usuarioDoSetorEsporte())
            ->get(route('placar.scout.sumula.print', $this->jogo))
            ->assertOk()
            ->assertSee('Resumo por quarter')
            ->assertSeeInOrder(['Quarter 1 —', 'Quarter 2 —'])
            ->assertSee('Ala da Casa');
    }

    /** Jogo sem evento nenhum não pode imprimir uma seção vazia. */
    public function test_jogo_sem_lances_nao_imprime_a_secao(): void
    {
        $this->actingAs($this->usuarioDoSetorEsporte())
            ->get(route('placar.scout.sumula.print', $this->jogo))
            ->assertOk()
            ->assertDontSee('Resumo por quarter');
    }
}
