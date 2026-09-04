<?php

namespace Tests\Feature\Placar\Api;

use App\Models\Placar\ApiCliente;
use App\Models\Placar\Elenco;
use App\Models\Placar\Equipe;
use App\Models\Placar\Escalacao;
use App\Models\Placar\Jogador;
use App\Models\Placar\Jogo;
use App\Models\Placar\JogoEvento;
use App\Models\Placar\Modalidade;
use App\Models\Placar\Time;
use App\Support\Placar\PlacarAbilities;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MigratesPlacarSchema;
use Tests\TestCase;

/**
 * Base de substituição e tempo técnico para o placar operar.
 *
 * Os dois são eventos DE UM TIME DENTRO DE UM PERÍODO — é isso que o
 * contrato passa a exigir — e o que o placar precisa de volta é o estado:
 * quem está em quadra depois das trocas e quantos tempos ainda restam.
 * GET /jogos/{jogo}/situacao responde isso, para nenhum cliente ter de
 * refazer a conta a partir do log (é assim que duas telas discordam).
 */
class SubstituicaoETimeoutTest extends TestCase
{
    use MigratesPlacarSchema;

    private Jogo $jogo;
    private Time $timeCasa;
    private Time $timeFora;

    /** @var list<Jogador> elenco do time da casa, na ordem cadastrada */
    private array $casa = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->migratePlacarSchema();

        Sanctum::actingAs(ApiCliente::create(['nome' => 'node', 'ativo' => true]), [PlacarAbilities::OPERAR]);

        $this->jogo = $this->partida('futsal', 'Futsal');
    }

    private function partida(string $slug, string $nome): Jogo
    {
        $modalidade = Modalidade::firstOrCreate(['slug' => $slug], ['nome' => $nome, 'ativo' => true]);

        $equipeA = Equipe::create(['nome' => "Casa {$slug}", 'ativo' => true]);
        $equipeB = Equipe::create(['nome' => "Fora {$slug}", 'ativo' => true]);

        $this->timeCasa = Time::create(['equipe_id' => $equipeA->id, 'modalidade_id' => $modalidade->id, 'categoria' => 'Adulto', 'ativo' => true]);
        $this->timeFora = Time::create(['equipe_id' => $equipeB->id, 'modalidade_id' => $modalidade->id, 'categoria' => 'Adulto', 'ativo' => true]);

        $this->casa = [];
        foreach (range(1, 9) as $numero) {
            $jogador = Jogador::create([
                'equipe_id' => $equipeA->id, 'modalidade_id' => $modalidade->id,
                'nome' => "Jogador {$numero} da Casa", 'ativo' => true,
            ]);
            Elenco::create([
                'time_id' => $this->timeCasa->id, 'jogador_id' => $jogador->id,
                'temporada' => now()->year, 'numero' => (string) $numero, 'ativo' => true,
            ]);
            $this->casa[] = $jogador;
        }

        return Jogo::create([
            'modalidade_id' => $modalidade->id,
            'time_casa_id' => $this->timeCasa->id, 'time_fora_id' => $this->timeFora->id,
            'data_hora' => now(), 'status' => Jogo::STATUS_AO_VIVO, 'criado_em_campo' => false,
        ]);
    }

    /** Escala os `$quantos` primeiros como titulares e o resto no banco. */
    private function escalar(int $quantos): void
    {
        foreach ($this->casa as $indice => $jogador) {
            Escalacao::create([
                'jogo_id' => $this->jogo->id, 'time_id' => $this->timeCasa->id,
                'jogador_id' => $jogador->id, 'numero' => (string) ($indice + 1),
                'titular' => $indice < $quantos, 'capitao' => $indice === 0,
            ]);
        }
    }

    private function enviar(array $eventos)
    {
        return $this->postJson("/api/placar/jogos/{$this->jogo->id}/eventos", ['eventos' => $eventos]);
    }

    private function evento(string $tipo, array $extras = []): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'sequencia' => JogoEvento::where('jogo_id', $this->jogo->id)->max('sequencia') + count($extras) + 1,
            'tipo' => $tipo,
            'ocorrido_em' => now()->toDateTimeString(),
            ...$extras,
        ];
    }

    private function situacao(): array
    {
        return $this->getJson("/api/placar/jogos/{$this->jogo->id}/situacao")->assertOk()->json();
    }

    /* ============================== Contrato ================================ */

    public function test_timeout_sem_time_id_e_rejeitado(): void
    {
        $resposta = $this->enviar([$this->evento(JogoEvento::TIPO_TIMEOUT, ['periodo' => 1])]);

        $resposta->assertOk()->assertJsonCount(1, 'rejeitados');
        $this->assertStringContainsString('time_id', $resposta->json('rejeitados.0.motivo'));
    }

    /** Sem período o contador de "tempos restantes" não fecha. */
    public function test_timeout_sem_periodo_e_rejeitado(): void
    {
        $resposta = $this->enviar([$this->evento(JogoEvento::TIPO_TIMEOUT, ['time_id' => $this->timeCasa->id])]);

        $resposta->assertOk()->assertJsonCount(1, 'rejeitados');
        $this->assertStringContainsString('periodo', $resposta->json('rejeitados.0.motivo'));
    }

    public function test_substituicao_sem_quem_sai_e_quem_entra_e_rejeitada(): void
    {
        $resposta = $this->enviar([$this->evento(JogoEvento::TIPO_SUBSTITUICAO, [
            'time_id' => $this->timeCasa->id, 'periodo' => 1,
        ])]);

        $resposta->assertOk()->assertJsonCount(1, 'rejeitados');
        $this->assertStringContainsString('sai_jogador_id', $resposta->json('rejeitados.0.motivo'));
    }

    public function test_substituicao_do_jogador_por_ele_mesmo_e_rejeitada(): void
    {
        $resposta = $this->enviar([$this->evento(JogoEvento::TIPO_SUBSTITUICAO, [
            'time_id' => $this->timeCasa->id, 'periodo' => 1,
            'payload' => ['sai_jogador_id' => $this->casa[0]->id, 'entra_jogador_id' => $this->casa[0]->id],
        ])]);

        $resposta->assertOk()->assertJsonCount(1, 'rejeitados');
        $this->assertStringContainsString('mesmo jogador', $resposta->json('rejeitados.0.motivo'));
    }

    public function test_substituicao_com_jogador_inexistente_e_rejeitada(): void
    {
        $resposta = $this->enviar([$this->evento(JogoEvento::TIPO_SUBSTITUICAO, [
            'time_id' => $this->timeCasa->id, 'periodo' => 1,
            'payload' => ['sai_jogador_id' => $this->casa[0]->id, 'entra_jogador_id' => 999_999],
        ])]);

        $resposta->assertOk()->assertJsonCount(1, 'rejeitados');
        $this->assertStringContainsString('inexistente', $resposta->json('rejeitados.0.motivo'));
    }

    /** Um evento de controle inválido não pode derrubar o ponto do mesmo lote. */
    public function test_troca_invalida_nao_derruba_o_resto_do_lote(): void
    {
        $resposta = $this->enviar([
            $this->evento(JogoEvento::TIPO_PONTO, [
                'time_id' => $this->timeCasa->id, 'jogador_id' => $this->casa[0]->id,
                'valor' => 1, 'periodo' => 1, 'cronometro_ms' => 60_000,
            ]),
            $this->evento(JogoEvento::TIPO_SUBSTITUICAO, ['time_id' => $this->timeCasa->id, 'periodo' => 1]),
        ]);

        $resposta->assertOk()->assertJsonCount(1, 'aceitos')->assertJsonCount(1, 'rejeitados');
        $this->assertSame(1, $this->jogo->refresh()->placar_casa);
    }

    /* ============================== Situação ================================ */

    /** Sem escalação não há titular, e portanto não há como dizer quem está em quadra. */
    public function test_sem_escalacao_a_situacao_diz_que_nao_ha_quadra_definida(): void
    {
        $situacao = $this->situacao();

        $this->assertFalse($situacao['time_casa']['escalacao_definida']);
        $this->assertSame([], $situacao['time_casa']['em_quadra']);
        $this->assertCount(9, $situacao['time_casa']['no_banco']);
    }

    public function test_com_escalacao_a_quadra_e_a_dos_titulares(): void
    {
        $this->escalar(5);

        $situacao = $this->situacao();

        $this->assertTrue($situacao['time_casa']['escalacao_definida']);
        $this->assertCount(5, $situacao['time_casa']['em_quadra']);
        $this->assertCount(4, $situacao['time_casa']['no_banco']);
        $this->assertTrue($situacao['time_casa']['em_quadra'][0]['capitao']);
    }

    public function test_substituicao_troca_quem_esta_em_quadra(): void
    {
        $this->escalar(5);
        $sai = $this->casa[1];
        $entra = $this->casa[6];

        $this->enviar([$this->evento(JogoEvento::TIPO_SUBSTITUICAO, [
            'time_id' => $this->timeCasa->id, 'periodo' => 1,
            'payload' => ['sai_jogador_id' => $sai->id, 'entra_jogador_id' => $entra->id],
        ])])->assertOk()->assertJsonCount(1, 'aceitos');

        $emQuadra = collect($this->situacao()['time_casa']['em_quadra'])->pluck('jogador_id');

        $this->assertCount(5, $emQuadra);
        $this->assertFalse($emQuadra->contains($sai->id));
        $this->assertTrue($emQuadra->contains($entra->id));
    }

    /** Troca estornada não aconteceu: quem tinha saído volta para a quadra. */
    public function test_substituicao_estornada_devolve_o_jogador_a_quadra(): void
    {
        $this->escalar(5);
        $sai = $this->casa[1];
        $entra = $this->casa[6];

        $troca = $this->evento(JogoEvento::TIPO_SUBSTITUICAO, [
            'time_id' => $this->timeCasa->id, 'periodo' => 1,
            'payload' => ['sai_jogador_id' => $sai->id, 'entra_jogador_id' => $entra->id],
        ]);
        $this->enviar([$troca])->assertOk();

        $this->enviar([$this->evento(JogoEvento::TIPO_ESTORNO, [
            'payload' => ['evento_uuid' => $troca['uuid'], 'motivo' => 'troca registrada por engano'],
        ])])->assertOk();

        $emQuadra = collect($this->situacao()['time_casa']['em_quadra'])->pluck('jogador_id');

        $this->assertTrue($emQuadra->contains($sai->id));
        $this->assertFalse($emQuadra->contains($entra->id));
    }

    public function test_tempo_tecnico_desconta_do_limite_do_periodo(): void
    {
        $antes = $this->situacao()['time_casa']['timeouts'];
        // Futsal: um tempo por time em cada tempo de jogo.
        $this->assertSame(1, $antes['limite_por_periodo']);
        $this->assertSame(1, $antes['restantes_no_periodo']);

        $this->enviar([$this->evento(JogoEvento::TIPO_TIMEOUT, [
            'time_id' => $this->timeCasa->id, 'periodo' => 1,
        ])])->assertOk()->assertJsonCount(1, 'aceitos');

        $depois = $this->situacao()['time_casa']['timeouts'];
        $this->assertSame(1, $depois['usados_no_periodo']);
        $this->assertSame(0, $depois['restantes_no_periodo']);
        // O tempo é do time que pediu — o outro continua com o dele.
        $this->assertSame(1, $this->situacao()['time_fora']['timeouts']['restantes_no_periodo']);
    }

    /** Cada período recomeça a conta — e o total do jogo não. */
    public function test_o_periodo_seguinte_devolve_os_tempos(): void
    {
        $this->enviar([$this->evento(JogoEvento::TIPO_TIMEOUT, ['time_id' => $this->timeCasa->id, 'periodo' => 1])])->assertOk();
        $this->enviar([$this->evento(JogoEvento::TIPO_PERIODO, ['periodo' => 2, 'valor' => 2])])->assertOk();

        $situacao = $this->situacao();

        $this->assertSame(2, $situacao['periodo_atual']);
        $this->assertSame(0, $situacao['time_casa']['timeouts']['usados_no_periodo']);
        $this->assertSame(1, $situacao['time_casa']['timeouts']['restantes_no_periodo']);
        $this->assertSame(1, $situacao['time_casa']['timeouts']['usados_no_jogo']);
    }

    public function test_tempo_estornado_volta_para_a_conta(): void
    {
        $timeout = $this->evento(JogoEvento::TIPO_TIMEOUT, ['time_id' => $this->timeCasa->id, 'periodo' => 1]);
        $this->enviar([$timeout])->assertOk();

        $this->enviar([$this->evento(JogoEvento::TIPO_ESTORNO, [
            'payload' => ['evento_uuid' => $timeout['uuid'], 'motivo' => 'tempo marcado para o time errado'],
        ])])->assertOk();

        $this->assertSame(1, $this->situacao()['time_casa']['timeouts']['restantes_no_periodo']);
    }

    /** Futsal troca à vontade — limite null, e não zero. */
    public function test_futsal_nao_limita_substituicoes(): void
    {
        $substituicoes = $this->situacao()['time_casa']['substituicoes'];

        $this->assertNull($substituicoes['limite_por_periodo']);
        $this->assertNull($substituicoes['restantes_no_periodo']);
    }

    public function test_volei_tem_dois_tempos_e_seis_substituicoes_por_set(): void
    {
        $this->jogo = $this->partida('volei', 'Vôlei');

        $situacao = $this->situacao();

        $this->assertSame('set', $situacao['nome_do_periodo']);
        $this->assertSame(2, $situacao['time_casa']['timeouts']['limite_por_periodo']);
        $this->assertSame(6, $situacao['time_casa']['substituicoes']['limite_por_periodo']);
    }

    /** A súmula tem de dizer quem saiu e quem entrou, não só "substituição". */
    public function test_a_sumula_mostra_os_dois_lados_da_troca(): void
    {
        $this->escalar(5);
        $sai = $this->casa[1];
        $entra = $this->casa[6];

        $this->enviar([$this->evento(JogoEvento::TIPO_SUBSTITUICAO, [
            'time_id' => $this->timeCasa->id, 'periodo' => 1,
            'payload' => ['sai_jogador_id' => $sai->id, 'entra_jogador_id' => $entra->id],
        ])])->assertOk();

        $evento = collect($this->getJson("/api/placar/jogos/{$this->jogo->id}/sumula")->assertOk()->json('eventos'))
            ->firstWhere('tipo', JogoEvento::TIPO_SUBSTITUICAO);

        $this->assertSame($sai->id, $evento['substituicao']['sai']['jogador_id']);
        $this->assertSame($entra->id, $evento['substituicao']['entra']['jogador_id']);
        $this->assertSame($entra->nomeExibicaoResolvido(), $evento['substituicao']['entra']['nome_exibicao']);
    }
}
