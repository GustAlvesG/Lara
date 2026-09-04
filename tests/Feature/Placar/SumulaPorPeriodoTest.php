<?php

namespace Tests\Feature\Placar;

use App\Models\Placar\ApiCliente;
use App\Models\Placar\Elenco;
use App\Models\Placar\Equipe;
use App\Models\Placar\Jogador;
use App\Models\Placar\Jogo;
use App\Models\Placar\JogoEvento;
use App\Models\Placar\Modalidade;
use App\Models\Placar\Time;
use App\Services\Placar\ScoutService;
use App\Services\Placar\Vocabulario;
use App\Support\Placar\PlacarAbilities;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MigratesPlacarSchema;
use Tests\Concerns\MocksPlacarUser;
use Tests\TestCase;

/**
 * A súmula fala a língua do esporte e recorta por parcial.
 *
 * No futsal se faz gol, no basquete cesta (e o valor faz parte do nome), no
 * vôlei ponto; o período é período, quarter e set. E "quem produziu no 3º
 * quarter" não se responde olhando a soma do jogo inteiro — por isso o
 * filtro por parcial vale também para os totais por jogador.
 */
class SumulaPorPeriodoTest extends TestCase
{
    use MigratesPlacarSchema;
    use MocksPlacarUser;

    private Time $timeCasa;
    private Time $timeFora;
    private Jogador $craque;
    private Jogo $jogo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migratePlacarSchema();
    }

    private function montarPartida(string $slug, string $nome): void
    {
        $modalidade = Modalidade::firstOrCreate(['slug' => $slug], ['nome' => $nome, 'ativo' => true]);

        $equipeA = Equipe::create(['nome' => "Casa {$slug}", 'ativo' => true]);
        $equipeB = Equipe::create(['nome' => "Fora {$slug}", 'ativo' => true]);

        $this->timeCasa = Time::create(['equipe_id' => $equipeA->id, 'modalidade_id' => $modalidade->id, 'categoria' => 'Adulto', 'ativo' => true]);
        $this->timeFora = Time::create(['equipe_id' => $equipeB->id, 'modalidade_id' => $modalidade->id, 'categoria' => 'Adulto', 'ativo' => true]);

        $this->craque = Jogador::create([
            'equipe_id' => $equipeA->id, 'modalidade_id' => $modalidade->id,
            'nome' => 'Craque da Casa', 'ativo' => true,
        ]);
        Elenco::create([
            'time_id' => $this->timeCasa->id, 'jogador_id' => $this->craque->id,
            'temporada' => now()->year, 'numero' => '10', 'ativo' => true,
        ]);

        $this->jogo = Jogo::create([
            'modalidade_id' => $modalidade->id,
            'time_casa_id' => $this->timeCasa->id, 'time_fora_id' => $this->timeFora->id,
            'data_hora' => now(), 'status' => Jogo::STATUS_ENCERRADO, 'criado_em_campo' => false,
        ]);
    }

    private function lance(string $tipo, int $periodo, ?int $valor = 1, ?Jogador $jogador = null, ?Time $time = null): void
    {
        JogoEvento::create([
            'uuid' => (string) Str::uuid(),
            'jogo_id' => $this->jogo->id,
            'sequencia' => JogoEvento::where('jogo_id', $this->jogo->id)->max('sequencia') + 1,
            'tipo' => $tipo,
            'time_id' => ($time ?? $this->timeCasa)->id,
            'jogador_id' => ($jogador ?? $this->craque)->id,
            'valor' => $valor, 'periodo' => $periodo,
            'cronometro_ms' => 60_000, 'ocorrido_em' => now(),
        ]);
    }

    private function sumula(?int $timeId = null, ?int $periodo = null): array
    {
        return app(ScoutService::class)->sumula($this->jogo->fresh(), $timeId, $periodo);
    }

    /* ============================= Vocabulário ============================== */

    public function test_futsal_faz_gol_em_periodo(): void
    {
        $this->montarPartida('futsal', 'Futsal');
        $this->lance(JogoEvento::TIPO_PONTO, 1);

        $sumula = $this->sumula();

        $this->assertSame('Gol', $sumula['eventos'][0]['rotulo']);
        $this->assertSame('período', $sumula['vocabulario']['periodo']);
        $this->assertSame('gols', $sumula['vocabulario']['pontos']);
        $this->assertTrue($sumula['vocabulario']['tem_falta']);
    }

    /** No basquete o valor faz parte do nome do lance. */
    public function test_basquete_faz_cesta_de_dois_ou_tres_em_quarter(): void
    {
        $this->montarPartida('basquete', 'Basquete');
        $this->lance(JogoEvento::TIPO_PONTO, 1, valor: 3);
        $this->lance(JogoEvento::TIPO_PONTO, 1, valor: 2);

        $sumula = $this->sumula();

        $this->assertSame('Cesta de 3', $sumula['eventos'][0]['rotulo']);
        $this->assertSame('Cesta de 2', $sumula['eventos'][1]['rotulo']);
        $this->assertSame('quarter', $sumula['vocabulario']['periodo']);
        $this->assertSame('pontos', $sumula['vocabulario']['pontos']);
    }

    public function test_volei_faz_ponto_em_set_e_nao_tem_falta(): void
    {
        $this->montarPartida('volei', 'Vôlei');
        $this->lance(JogoEvento::TIPO_PONTO, 1);
        $this->lance(JogoEvento::TIPO_SET, 1, valor: 1);

        $sumula = $this->sumula();

        $this->assertSame('Ponto', $sumula['eventos'][0]['rotulo']);
        $this->assertSame('Set vencido', $sumula['eventos'][1]['rotulo']);
        $this->assertSame('set', $sumula['vocabulario']['periodo']);
        $this->assertFalse($sumula['vocabulario']['tem_falta']);
    }

    public function test_o_vocabulario_nomeia_os_marcos_do_jogo(): void
    {
        $this->assertSame('Início do quarter', Vocabulario::evento('basquete', JogoEvento::TIPO_PERIODO));
        $this->assertSame('Início do set', Vocabulario::evento('volei', JogoEvento::TIPO_CRONO_SET));
        $this->assertSame('Tempo técnico', Vocabulario::evento('futsal', JogoEvento::TIPO_TIMEOUT));
        $this->assertSame('Substituição', Vocabulario::evento('futsal', JogoEvento::TIPO_SUBSTITUICAO));
    }

    /* ========================== Filtro por parcial ========================== */

    public function test_o_filtro_por_parcial_recorta_lances_e_totais(): void
    {
        $this->montarPartida('basquete', 'Basquete');
        $this->lance(JogoEvento::TIPO_PONTO, 1, valor: 3);
        $this->lance(JogoEvento::TIPO_PONTO, 2, valor: 2);
        $this->lance(JogoEvento::TIPO_PONTO, 2, valor: 2);

        $segundoQuarter = $this->sumula(periodo: 2);

        $this->assertSame(2, $segundoQuarter['periodo']);
        $this->assertCount(2, $segundoQuarter['eventos']);
        // 4 pontos no 2º quarter — e não os 7 do jogo.
        $this->assertSame(4, $segundoQuarter['totais_por_jogador']['time_casa'][0]['pontos']);
    }

    /** O placar por parcial é a referência de onde o recorte se encaixa. */
    public function test_o_placar_por_parcial_continua_completo_no_filtro(): void
    {
        $this->montarPartida('futsal', 'Futsal');
        $this->lance(JogoEvento::TIPO_PONTO, 1);
        $this->lance(JogoEvento::TIPO_PONTO, 2);

        $sumula = $this->sumula(periodo: 1);

        $this->assertCount(2, $sumula['placar_por_periodo']);
        $this->assertSame([1, 2], $sumula['periodos_disponiveis']);
    }

    /** Jogo interrompido não pode oferecer aba de parcial que não houve. */
    public function test_so_lista_as_parciais_que_a_partida_teve(): void
    {
        $this->montarPartida('basquete', 'Basquete');
        $this->lance(JogoEvento::TIPO_PONTO, 1, valor: 2);
        $this->lance(JogoEvento::TIPO_PONTO, 2, valor: 2);

        $this->assertSame([1, 2], $this->sumula()['periodos_disponiveis']);
    }

    public function test_parcial_sem_lance_nenhum_volta_vazia_sem_erro(): void
    {
        $this->montarPartida('futsal', 'Futsal');
        $this->lance(JogoEvento::TIPO_PONTO, 1);

        $sumula = $this->sumula(periodo: 4);

        $this->assertSame([], $sumula['eventos']);
        $this->assertSame([], $sumula['totais_por_jogador']['time_casa']);
        // O cabeçalho e o placar continuam de pé.
        $this->assertSame($this->jogo->id, $sumula['jogo']['id']);
    }

    /** Os dois recortes se combinam: "o que o meu time fez no 2º tempo". */
    public function test_time_e_parcial_se_combinam(): void
    {
        $this->montarPartida('futsal', 'Futsal');
        $this->lance(JogoEvento::TIPO_PONTO, 2);
        $this->lance(JogoEvento::TIPO_PONTO, 2, time: $this->timeFora, jogador: $this->craque);

        $sumula = $this->sumula($this->timeCasa->id, 2);

        $this->assertCount(1, $sumula['eventos']);
        $this->assertSame([], $sumula['totais_por_jogador']['time_fora']);
        $this->assertSame(1, $sumula['totais_por_jogador']['time_casa'][0]['pontos']);
    }

    /* =============================== Telas ================================= */

    public function test_a_tela_oferece_as_abas_de_parcial_com_o_nome_do_esporte(): void
    {
        $this->montarPartida('basquete', 'Basquete');
        $this->lance(JogoEvento::TIPO_PONTO, 1, valor: 3);
        $this->lance(JogoEvento::TIPO_PONTO, 2, valor: 2);

        $this->actingAs($this->usuarioDoSetorEsporte())
            ->get(route('placar.scout.sumula', $this->jogo))
            ->assertOk()
            ->assertSee('Jogo todo')
            ->assertSee('Quarter 1')
            ->assertSee('Quarter 2')
            ->assertSee('Cesta de 3');
    }

    public function test_a_tela_filtrada_mostra_so_a_parcial(): void
    {
        $this->montarPartida('volei', 'Vôlei');
        $this->lance(JogoEvento::TIPO_PONTO, 1);
        $this->lance(JogoEvento::TIPO_PONTO, 2);

        $this->actingAs($this->usuarioDoSetorEsporte())
            ->get(route('placar.scout.sumula', [$this->jogo, 'periodo' => 2]))
            ->assertOk()
            ->assertSee('Set 2')
            // Vôlei não tem falta: a coluna não aparece.
            ->assertDontSee('faltas');
    }

    public function test_a_impressao_aceita_a_parcial(): void
    {
        $this->montarPartida('futsal', 'Futsal');
        $this->lance(JogoEvento::TIPO_PONTO, 2);

        $this->actingAs($this->usuarioDoSetorEsporte())
            ->get(route('placar.scout.sumula.print', [$this->jogo, 'periodo' => 2]))
            ->assertOk()
            ->assertSee('Período 2')
            ->assertSee('Gol');
    }

    public function test_periodo_invalido_na_tela_e_recusado(): void
    {
        $this->montarPartida('futsal', 'Futsal');

        $this->actingAs($this->usuarioDoSetorEsporte())
            ->get(route('placar.scout.sumula', [$this->jogo, 'periodo' => 'segundo']))
            ->assertNotFound();
    }

    /* ================================ API ================================== */

    public function test_a_api_recorta_por_parcial(): void
    {
        Sanctum::actingAs(ApiCliente::create(['nome' => 'node', 'ativo' => true]), [PlacarAbilities::OPERAR]);
        $this->montarPartida('basquete', 'Basquete');
        $this->lance(JogoEvento::TIPO_PONTO, 1, valor: 3);
        $this->lance(JogoEvento::TIPO_PONTO, 3, valor: 2);

        $body = $this->getJson("/api/placar/jogos/{$this->jogo->id}/sumula?periodo=3")
            ->assertOk()
            ->json();

        $this->assertSame(3, $body['periodo']);
        $this->assertSame([1, 3], $body['periodos_disponiveis']);
        $this->assertCount(1, $body['eventos']);
        $this->assertSame('Cesta de 2', $body['eventos'][0]['rotulo']);
        $this->assertSame('quarter', $body['vocabulario']['periodo']);
    }

    public function test_a_api_recusa_periodo_que_nao_e_numero(): void
    {
        Sanctum::actingAs(ApiCliente::create(['nome' => 'node', 'ativo' => true]), [PlacarAbilities::OPERAR]);
        $this->montarPartida('futsal', 'Futsal');

        $this->getJson("/api/placar/jogos/{$this->jogo->id}/sumula?periodo=0")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('periodo');
    }
}
