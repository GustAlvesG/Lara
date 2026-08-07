<?php

namespace Tests\Feature\Placar\Api;

use App\Models\Placar\ApiCliente;
use App\Models\Placar\Equipe;
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
 * POST /placar/jogos/{jogo}/eventos — o endpoint mais importante da API.
 * Idempotência por uuid e tolerância a evento inválido dentro do lote.
 */
class EventosLoteTest extends TestCase
{
    use MigratesPlacarSchema;

    private Jogo $jogo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migratePlacarSchema();

        $futsal = Modalidade::create(['nome' => 'Futsal', 'slug' => 'futsal', 'ativo' => true]);
        $equipeA = Equipe::create(['nome' => 'Equipe A', 'ativo' => true]);
        $equipeB = Equipe::create(['nome' => 'Equipe B', 'ativo' => true]);
        $timeA = Time::create(['equipe_id' => $equipeA->id, 'modalidade_id' => $futsal->id, 'categoria' => 'Adulto', 'ativo' => true]);
        $timeB = Time::create(['equipe_id' => $equipeB->id, 'modalidade_id' => $futsal->id, 'categoria' => 'Adulto', 'ativo' => true]);

        $this->jogo = Jogo::create([
            'modalidade_id' => $futsal->id, 'time_casa_id' => $timeA->id, 'time_fora_id' => $timeB->id,
            'data_hora' => now(), 'status' => Jogo::STATUS_AO_VIVO, 'criado_em_campo' => false,
        ]);

        $cliente = ApiCliente::create(['nome' => 'node-teste', 'ativo' => true]);
        Sanctum::actingAs($cliente, [PlacarAbilities::OPERAR]);
    }

    public function test_reenviar_o_mesmo_lote_e_idempotente(): void
    {
        $uuid = (string) Str::uuid();
        $lote = ['eventos' => [[
            'uuid' => $uuid, 'sequencia' => 1, 'tipo' => JogoEvento::TIPO_PONTO,
            'time_id' => $this->jogo->time_casa_id, 'valor' => 1, 'periodo' => 1,
            'ocorrido_em' => now()->toDateTimeString(),
        ]]];

        $primeiro = $this->postJson("/api/placar/jogos/{$this->jogo->id}/eventos", $lote);
        $primeiro->assertOk()->assertJson(['aceitos' => [$uuid], 'duplicados' => [], 'rejeitados' => []]);

        $segundo = $this->postJson("/api/placar/jogos/{$this->jogo->id}/eventos", $lote);
        $segundo->assertOk()->assertJson(['aceitos' => [], 'duplicados' => [$uuid], 'rejeitados' => []]);

        // Não duplicou a linha no banco, nem contou o ponto duas vezes no placar.
        $this->assertSame(1, JogoEvento::where('uuid', $uuid)->count());
        $this->assertSame(1, $this->jogo->refresh()->placar_casa);
    }

    public function test_um_evento_invalido_no_meio_do_lote_nao_derruba_os_demais(): void
    {
        $uuidValido1 = (string) Str::uuid();
        $uuidValido2 = (string) Str::uuid();

        $resposta = $this->postJson("/api/placar/jogos/{$this->jogo->id}/eventos", ['eventos' => [
            [
                'uuid' => $uuidValido1, 'sequencia' => 1, 'tipo' => JogoEvento::TIPO_PONTO,
                'time_id' => $this->jogo->time_casa_id, 'valor' => 1, 'periodo' => 1,
                'ocorrido_em' => now()->toDateTimeString(),
            ],
            [
                // tipo inexistente — deve ser rejeitado, mas não pode
                // impedir a inserção dos eventos válidos antes e depois dele.
                'uuid' => (string) Str::uuid(), 'sequencia' => 2, 'tipo' => 'chute_a_gol_inexistente',
                'ocorrido_em' => now()->toDateTimeString(),
            ],
            [
                'uuid' => $uuidValido2, 'sequencia' => 3, 'tipo' => JogoEvento::TIPO_PONTO,
                'time_id' => $this->jogo->time_fora_id, 'valor' => 1, 'periodo' => 1,
                'ocorrido_em' => now()->toDateTimeString(),
            ],
        ]]);

        $resposta->assertOk();
        $resposta->assertJsonCount(2, 'aceitos');
        $resposta->assertJsonCount(1, 'rejeitados');
        $this->assertSame([$uuidValido1, $uuidValido2], $resposta->json('aceitos'));

        $this->assertSame(1, $this->jogo->refresh()->placar_casa);
        $this->assertSame(1, $this->jogo->placar_fora);
    }

    public function test_valor_de_ponto_invalido_para_a_modalidade_e_rejeitado(): void
    {
        // Futsal só aceita ponto de valor 1 — basquete aceitaria 3.
        $resposta = $this->postJson("/api/placar/jogos/{$this->jogo->id}/eventos", ['eventos' => [[
            'uuid' => (string) Str::uuid(), 'sequencia' => 1, 'tipo' => JogoEvento::TIPO_PONTO,
            'time_id' => $this->jogo->time_casa_id, 'valor' => 3, 'periodo' => 1,
            'ocorrido_em' => now()->toDateTimeString(),
        ]]]);

        $resposta->assertOk()->assertJsonCount(1, 'rejeitados')->assertJsonCount(0, 'aceitos');
    }
}
