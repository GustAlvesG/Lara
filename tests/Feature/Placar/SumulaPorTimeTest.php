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
use App\Support\Placar\PlacarAbilities;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MigratesPlacarSchema;
use Tests\Concerns\MocksPlacarUser;
use Tests\TestCase;

/**
 * A súmula sai completa ou recortada em um dos times — cada equipe costuma
 * querer só a sua para arquivar ou entregar ao técnico.
 *
 * O recorte muda os lances e os totais; o placar e o cabeçalho continuam
 * completos, porque uma súmula que não diz contra quem se jogou não serve.
 */
class SumulaPorTimeTest extends TestCase
{
    use MigratesPlacarSchema;
    use MocksPlacarUser;

    private Time $timeCasa;
    private Time $timeFora;
    private Jogador $daCasa;
    private Jogador $doFora;
    private Jogo $jogo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migratePlacarSchema();

        $futsal = Modalidade::create(['nome' => 'Futsal', 'slug' => 'futsal', 'ativo' => true]);
        $equipeA = Equipe::create(['nome' => 'Equipe da Casa', 'ativo' => true]);
        $equipeB = Equipe::create(['nome' => 'Equipe Visitante', 'ativo' => true]);

        $this->timeCasa = Time::create(['equipe_id' => $equipeA->id, 'modalidade_id' => $futsal->id, 'categoria' => 'Adulto', 'ativo' => true]);
        $this->timeFora = Time::create(['equipe_id' => $equipeB->id, 'modalidade_id' => $futsal->id, 'categoria' => 'Adulto', 'ativo' => true]);

        $this->daCasa = $this->jogadorDe($this->timeCasa, 'Camisa Dez da Casa', '10');
        $this->doFora = $this->jogadorDe($this->timeFora, 'Camisa Sete Visitante', '7');

        $this->jogo = Jogo::create([
            'modalidade_id' => $futsal->id,
            'time_casa_id' => $this->timeCasa->id,
            'time_fora_id' => $this->timeFora->id,
            'data_hora' => now(), 'status' => Jogo::STATUS_ENCERRADO, 'criado_em_campo' => false,
        ]);
    }

    private function jogadorDe(Time $time, string $nome, string $numero): Jogador
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

    private function lance(string $tipo, int $sequencia, ?Time $time, ?Jogador $jogador, int $cronometroMs = 60_000, ?int $valor = 1): JogoEvento
    {
        return JogoEvento::create([
            'uuid' => (string) Str::uuid(), 'jogo_id' => $this->jogo->id, 'sequencia' => $sequencia,
            'tipo' => $tipo, 'time_id' => $time?->id, 'jogador_id' => $jogador?->id,
            'valor' => $valor, 'periodo' => 1, 'cronometro_ms' => $cronometroMs, 'ocorrido_em' => now(),
        ]);
    }

    /** Um ponto de cada lado, mais um marco de jogo sem time. */
    private function partidaComLancesDosDoisTimes(): void
    {
        $this->lance(JogoEvento::TIPO_INICIO_JOGO, 1, null, null, 0, null);
        $this->lance(JogoEvento::TIPO_PONTO, 2, $this->timeCasa, $this->daCasa);
        $this->lance(JogoEvento::TIPO_PONTO, 3, $this->timeFora, $this->doFora);
        $this->lance(JogoEvento::TIPO_FALTA, 4, $this->timeFora, $this->doFora, 90_000, null);
    }

    private function sumula(?int $timeId = null): array
    {
        return app(ScoutService::class)->sumula($this->jogo->fresh(), $timeId);
    }

    public function test_sumula_completa_traz_os_dois_times(): void
    {
        $this->partidaComLancesDosDoisTimes();

        $sumula = $this->sumula();

        $this->assertNull($sumula['recorte']);
        $this->assertCount(4, $sumula['eventos']);
        $this->assertCount(1, $sumula['totais_por_jogador']['time_casa']);
        $this->assertCount(1, $sumula['totais_por_jogador']['time_fora']);
    }

    public function test_recorte_deixa_so_os_lances_do_time_e_os_marcos_do_jogo(): void
    {
        $this->partidaComLancesDosDoisTimes();

        $sumula = $this->sumula($this->timeCasa->id);

        $this->assertSame('casa', $sumula['recorte']['lado']);
        $this->assertSame($this->timeCasa->id, $sumula['recorte']['time_id']);

        // O ponto da casa e o início de jogo (que não é de time nenhum e é
        // o que dá referência de tempo à linha) — nada do adversário.
        $this->assertSame(
            [JogoEvento::TIPO_INICIO_JOGO, JogoEvento::TIPO_PONTO],
            array_column($sumula['eventos'], 'tipo'),
        );

        $this->assertCount(1, $sumula['totais_por_jogador']['time_casa']);
        $this->assertSame([], $sumula['totais_por_jogador']['time_fora']);
    }

    /** O placar é da partida, não do recorte: sem ele a súmula não se lê. */
    public function test_recorte_mantem_placar_e_cabecalho_completos(): void
    {
        $this->partidaComLancesDosDoisTimes();
        $this->jogo->update($this->jogo->calcularPlacar());

        $sumula = $this->sumula($this->timeFora->id);

        $this->assertSame(1, $sumula['jogo']['placar_casa']);
        $this->assertSame(1, $sumula['jogo']['placar_fora']);
        $this->assertSame($this->timeCasa->id, $sumula['jogo']['time_casa']['id']);
        $this->assertSame(
            ['periodo' => 1, 'placar_casa' => 1, 'placar_fora' => 1],
            $sumula['placar_por_periodo'][0],
        );
    }

    /**
     * O evento de estorno não é de time nenhum. Se o recorte fosse aplicado
     * antes de apurar os estornos, o lance revertido voltaria a valer na
     * súmula individual.
     */
    public function test_lance_estornado_continua_estornado_no_recorte(): void
    {
        $ponto = $this->lance(JogoEvento::TIPO_PONTO, 1, $this->timeCasa, $this->daCasa);
        JogoEvento::create([
            'uuid' => (string) Str::uuid(), 'jogo_id' => $this->jogo->id, 'sequencia' => 2,
            'tipo' => JogoEvento::TIPO_ESTORNO, 'ocorrido_em' => now(),
            'payload' => ['evento_uuid' => $ponto->uuid],
        ]);

        $sumula = $this->sumula($this->timeCasa->id);

        $this->assertTrue($sumula['eventos'][0]['estornado']);
        $this->assertSame([], $sumula['totais_por_jogador']['time_casa']);
    }

    public function test_a_tela_oferece_o_recorte_por_time(): void
    {
        $this->partidaComLancesDosDoisTimes();

        $this->actingAs($this->usuarioDoSetorEsporte())
            ->get(route('placar.scout.sumula', $this->jogo))
            ->assertOk()
            ->assertSee('Completa')
            ->assertSee('Camisa Dez da Casa')
            ->assertSee('Camisa Sete Visitante');
    }

    public function test_a_tela_recortada_esconde_o_adversario(): void
    {
        $this->partidaComLancesDosDoisTimes();

        $this->actingAs($this->usuarioDoSetorEsporte())
            ->get(route('placar.scout.sumula', [$this->jogo, 'time_id' => $this->timeCasa->id]))
            ->assertOk()
            ->assertSee('Camisa Dez da Casa')
            ->assertDontSee('Camisa Sete Visitante');
    }

    public function test_a_impressao_aceita_o_recorte(): void
    {
        $this->partidaComLancesDosDoisTimes();

        $this->actingAs($this->usuarioDoSetorEsporte())
            ->get(route('placar.scout.sumula.print', [$this->jogo, 'time_id' => $this->timeFora->id]))
            ->assertOk()
            ->assertSee('Recorte:')
            ->assertSee('Camisa Sete Visitante')
            ->assertDontSee('Camisa Dez da Casa');
    }

    /** Id de time que não é do jogo não pode devolver a completa em silêncio. */
    public function test_a_tela_recusa_time_que_nao_e_do_jogo(): void
    {
        $outro = Time::create([
            'equipe_id' => $this->timeCasa->equipe_id, 'modalidade_id' => $this->timeCasa->modalidade_id,
            'categoria' => 'Sub-15', 'ativo' => true,
        ]);

        $this->actingAs($this->usuarioDoSetorEsporte())
            ->get(route('placar.scout.sumula', [$this->jogo, 'time_id' => $outro->id]))
            ->assertNotFound();
    }

    public function test_a_api_devolve_a_sumula_recortada(): void
    {
        Sanctum::actingAs(ApiCliente::create(['nome' => 'node', 'ativo' => true]), [PlacarAbilities::OPERAR]);
        $this->partidaComLancesDosDoisTimes();

        $body = $this->getJson("/api/placar/jogos/{$this->jogo->id}/sumula?time_id={$this->timeFora->id}")
            ->assertOk()
            ->json();

        $this->assertSame('fora', $body['recorte']['lado']);
        $this->assertSame([], $body['totais_por_jogador']['time_casa']);
        $this->assertCount(1, $body['totais_por_jogador']['time_fora']);
    }

    public function test_a_api_sem_recorte_devolve_recorte_nulo(): void
    {
        Sanctum::actingAs(ApiCliente::create(['nome' => 'node', 'ativo' => true]), [PlacarAbilities::OPERAR]);
        $this->partidaComLancesDosDoisTimes();

        $this->getJson("/api/placar/jogos/{$this->jogo->id}/sumula")
            ->assertOk()
            ->assertJsonPath('recorte', null);
    }

    public function test_a_api_recusa_time_que_nao_e_do_jogo(): void
    {
        Sanctum::actingAs(ApiCliente::create(['nome' => 'node', 'ativo' => true]), [PlacarAbilities::OPERAR]);

        $outro = Time::create([
            'equipe_id' => $this->timeCasa->equipe_id, 'modalidade_id' => $this->timeCasa->modalidade_id,
            'categoria' => 'Sub-15', 'ativo' => true,
        ]);

        $this->getJson("/api/placar/jogos/{$this->jogo->id}/sumula?time_id={$outro->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('time_id');
    }
}
