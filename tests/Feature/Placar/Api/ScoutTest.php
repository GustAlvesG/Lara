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
 * O número da camisa (do elenco/escalação, não do jogador em si) precisa
 * aparecer nas três visões de scout — súmula, artilharia e perfil — tanto
 * quanto nome e foto. ScoutService é a única fonte dessas visões, então o
 * que é coberto aqui também vale para as telas web (Etapa 11).
 */
class ScoutTest extends TestCase
{
    use MigratesPlacarSchema;

    private Time $time;
    private Jogador $jogador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migratePlacarSchema();

        $cliente = ApiCliente::create(['nome' => 'node-teste', 'ativo' => true]);
        Sanctum::actingAs($cliente, [PlacarAbilities::OPERAR]);

        $futsal = Modalidade::create(['nome' => 'Futsal', 'slug' => 'futsal', 'ativo' => true]);
        $equipe = Equipe::create(['nome' => 'Equipe do Scout', 'ativo' => true]);
        $this->time = Time::create(['equipe_id' => $equipe->id, 'modalidade_id' => $futsal->id, 'categoria' => 'Adulto', 'ativo' => true]);
        $this->jogador = Jogador::create(['nome' => 'Camisa Dez', 'ativo' => true]);
        Elenco::create([
            'time_id' => $this->time->id, 'jogador_id' => $this->jogador->id,
            'temporada' => now()->year, 'numero' => '10', 'ativo' => true,
        ]);
    }

    public function test_sumula_devolve_o_numero_na_timeline_e_nos_totais(): void
    {
        $adversario = Time::create([
            'equipe_id' => $this->time->equipe_id, 'modalidade_id' => $this->time->modalidade_id,
            'categoria' => 'Sub-15', 'ativo' => true,
        ]);
        $jogo = Jogo::create([
            'modalidade_id' => $this->time->modalidade_id, 'time_casa_id' => $this->time->id, 'time_fora_id' => $adversario->id,
            'data_hora' => now(), 'status' => Jogo::STATUS_AO_VIVO, 'criado_em_campo' => false,
        ]);
        JogoEvento::create([
            'uuid' => (string) Str::uuid(), 'jogo_id' => $jogo->id, 'sequencia' => 1,
            'tipo' => JogoEvento::TIPO_PONTO, 'time_id' => $this->time->id, 'jogador_id' => $this->jogador->id,
            'valor' => 1, 'periodo' => 1, 'ocorrido_em' => now(),
        ]);

        $body = $this->getJson("/api/placar/jogos/{$jogo->id}/sumula")->assertOk()->json();

        $this->assertSame('10', $body['eventos'][0]['jogador']['numero']);
        $this->assertSame('10', $body['totais_por_jogador']['time_casa'][0]['numero']);
    }

    public function test_artilharia_devolve_o_numero_quando_filtrada_por_time(): void
    {
        $adversario = Time::create([
            'equipe_id' => $this->time->equipe_id, 'modalidade_id' => $this->time->modalidade_id,
            'categoria' => 'Sub-15', 'ativo' => true,
        ]);
        $jogo = Jogo::create([
            'modalidade_id' => $this->time->modalidade_id, 'time_casa_id' => $this->time->id, 'time_fora_id' => $adversario->id,
            'data_hora' => now(), 'status' => Jogo::STATUS_ENCERRADO, 'criado_em_campo' => false,
        ]);
        JogoEvento::create([
            'uuid' => (string) Str::uuid(), 'jogo_id' => $jogo->id, 'sequencia' => 1,
            'tipo' => JogoEvento::TIPO_PONTO, 'time_id' => $this->time->id, 'jogador_id' => $this->jogador->id,
            'valor' => 1, 'periodo' => 1, 'ocorrido_em' => now(),
        ]);

        // Sem filtro de time: número fica ambíguo (o mesmo jogador poderia
        // pontuar por times diferentes), então vem null de propósito.
        $semFiltro = $this->getJson('/api/placar/scout/artilharia')->assertOk()->json();
        $this->assertNull($semFiltro[0]['numero']);

        // Com o time_id do próprio evento: o número do elenco aparece.
        $comFiltro = $this->getJson("/api/placar/scout/artilharia?time_id={$this->time->id}")->assertOk()->json();
        $this->assertSame('10', $comFiltro[0]['numero']);
    }

    public function test_perfil_do_jogador_devolve_o_numero_do_elenco_ativo(): void
    {
        $body = $this->getJson("/api/placar/scout/jogadores/{$this->jogador->id}")->assertOk()->json();

        $this->assertSame('10', $body['numero']);
    }
}
