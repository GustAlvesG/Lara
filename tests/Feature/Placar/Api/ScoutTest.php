<?php

namespace Tests\Feature\Placar\Api;

use App\Models\Placar\ApiCliente;
use App\Models\Placar\Elenco;
use App\Models\Placar\Equipe;
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
 * Scout: a unidade é sempre a PARTIDA, não o ranking entre partidas.
 *
 * Cobre a súmula (número + minutagem na timeline e nos totais), a ficha de
 * atuação de um jogador numa partida específica, e a lista de partidas de
 * um jogador. Não há artilharia — foi removida de propósito.
 */
class ScoutTest extends TestCase
{
    use MigratesPlacarSchema;

    private Time $timeCasa;
    private Time $timeFora;
    private Jogador $jogador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migratePlacarSchema();

        $cliente = ApiCliente::create(['nome' => 'node-teste', 'ativo' => true]);
        Sanctum::actingAs($cliente, [PlacarAbilities::OPERAR]);

        $futsal = Modalidade::create(['nome' => 'Futsal', 'slug' => 'futsal', 'ativo' => true]);
        $equipeA = Equipe::create(['nome' => 'Equipe do Scout', 'ativo' => true]);
        $equipeB = Equipe::create(['nome' => 'Equipe Adversária', 'ativo' => true]);

        $this->timeCasa = Time::create(['equipe_id' => $equipeA->id, 'modalidade_id' => $futsal->id, 'categoria' => 'Adulto', 'ativo' => true]);
        $this->timeFora = Time::create(['equipe_id' => $equipeB->id, 'modalidade_id' => $futsal->id, 'categoria' => 'Adulto', 'ativo' => true]);

        $this->jogador = Jogador::create(['nome' => 'Camisa Dez', 'ativo' => true]);
        Elenco::create([
            'time_id' => $this->timeCasa->id, 'jogador_id' => $this->jogador->id,
            'temporada' => now()->year, 'numero' => '10', 'ativo' => true,
        ]);
    }

    private function criarJogo(string $status = Jogo::STATUS_AO_VIVO): Jogo
    {
        return Jogo::create([
            'modalidade_id' => $this->timeCasa->modalidade_id,
            'time_casa_id' => $this->timeCasa->id,
            'time_fora_id' => $this->timeFora->id,
            'data_hora' => now(), 'status' => $status, 'criado_em_campo' => false,
        ]);
    }

    private function lance(Jogo $jogo, string $tipo, int $sequencia, ?int $cronometroMs, ?int $valor = 1): JogoEvento
    {
        return JogoEvento::create([
            'uuid' => (string) Str::uuid(), 'jogo_id' => $jogo->id, 'sequencia' => $sequencia,
            'tipo' => $tipo, 'time_id' => $this->timeCasa->id, 'jogador_id' => $this->jogador->id,
            'valor' => $valor, 'periodo' => 1, 'cronometro_ms' => $cronometroMs, 'ocorrido_em' => now(),
        ]);
    }

    public function test_sumula_traz_numero_e_minutagem_na_timeline_e_nos_totais(): void
    {
        $jogo = $this->criarJogo();
        // 12min34s de jogo.
        $this->lance($jogo, JogoEvento::TIPO_PONTO, 1, 754_000);

        $body = $this->getJson("/api/placar/jogos/{$jogo->id}/sumula")->assertOk()->json();

        $this->assertSame('10', $body['eventos'][0]['jogador']['numero']);
        $this->assertSame('12:34', $body['eventos'][0]['minuto']);
        $this->assertSame('10', $body['totais_por_jogador']['time_casa'][0]['numero']);
    }

    public function test_atuacao_na_partida_lista_os_lances_minutados_e_soma_os_totais(): void
    {
        $jogo = $this->criarJogo();
        $this->lance($jogo, JogoEvento::TIPO_PONTO, 1, 60_000);
        $this->lance($jogo, JogoEvento::TIPO_FALTA, 2, 125_000, null);
        $this->lance($jogo, JogoEvento::TIPO_PONTO, 3, 600_000);

        $body = $this->getJson("/api/placar/jogos/{$jogo->id}/jogadores/{$this->jogador->id}/atuacao")
            ->assertOk()
            ->json();

        $this->assertSame('10', $body['jogador']['numero']);
        $this->assertSame($this->timeCasa->id, $body['jogador']['time_id']);
        $this->assertSame(2, $body['totais']['pontos']);
        $this->assertSame(1, $body['totais']['faltas']);

        $this->assertSame(['01:00', '02:05', '10:00'], array_column($body['lances'], 'minuto'));
    }

    public function test_lance_estornado_fica_na_ficha_marcado_mas_fora_dos_totais(): void
    {
        $jogo = $this->criarJogo();
        $ponto = $this->lance($jogo, JogoEvento::TIPO_PONTO, 1, 60_000);
        $this->lance($jogo, JogoEvento::TIPO_PONTO, 2, 120_000);

        JogoEvento::create([
            'uuid' => (string) Str::uuid(), 'jogo_id' => $jogo->id, 'sequencia' => 3,
            'tipo' => JogoEvento::TIPO_ESTORNO, 'ocorrido_em' => now(),
            'payload' => ['evento_uuid' => $ponto->uuid],
        ]);

        $body = $this->getJson("/api/placar/jogos/{$jogo->id}/jogadores/{$this->jogador->id}/atuacao")
            ->assertOk()
            ->json();

        // Só o ponto não estornado conta...
        $this->assertSame(1, $body['totais']['pontos']);
        // ...mas o estornado continua na ficha, marcado.
        $this->assertTrue($body['lances'][0]['estornado']);
        $this->assertFalse($body['lances'][1]['estornado']);
    }

    public function test_partidas_do_jogador_traz_uma_linha_por_jogo_com_os_totais(): void
    {
        $primeiro = $this->criarJogo(Jogo::STATUS_ENCERRADO);
        $this->lance($primeiro, JogoEvento::TIPO_PONTO, 1, 60_000);

        $segundo = $this->criarJogo(Jogo::STATUS_ENCERRADO);
        $this->lance($segundo, JogoEvento::TIPO_PONTO, 1, 60_000);
        $this->lance($segundo, JogoEvento::TIPO_PONTO, 2, 90_000);

        $body = $this->getJson("/api/placar/scout/jogadores/{$this->jogador->id}")->assertOk()->json();

        $this->assertCount(2, $body['partidas']);
        $this->assertSame(3, array_sum(array_column($body['partidas'], 'pontos')));
    }

    public function test_jogador_sem_nenhum_lance_devolve_lista_vazia_em_vez_de_erro(): void
    {
        $reserva = Jogador::create(['nome' => 'Nunca Entrou', 'ativo' => true]);

        $this->getJson("/api/placar/scout/jogadores/{$reserva->id}")
            ->assertOk()
            ->assertJsonPath('partidas', []);
    }

    public function test_a_rota_de_artilharia_nao_existe_mais(): void
    {
        $this->getJson('/api/placar/scout/artilharia')->assertNotFound();
    }
}
