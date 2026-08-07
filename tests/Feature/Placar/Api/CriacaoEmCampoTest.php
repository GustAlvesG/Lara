<?php

namespace Tests\Feature\Placar\Api;

use App\Models\Placar\ApiCliente;
use App\Models\Placar\Jogo;
use App\Models\Placar\Modalidade;
use App\Support\Placar\PlacarAbilities;
use Database\Seeders\ModalidadeSeeder;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MigratesPlacarSchema;
use Tests\TestCase;

/**
 * Modo avulso: jogo não planejado, cadastro completo em poucas chamadas,
 * sempre com criado_em_campo = true.
 */
class CriacaoEmCampoTest extends TestCase
{
    use MigratesPlacarSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migratePlacarSchema();
        (new ModalidadeSeeder())->run();

        $cliente = ApiCliente::create(['nome' => 'node-teste', 'ativo' => true]);
        Sanctum::actingAs($cliente, [PlacarAbilities::OPERAR]);
    }

    public function test_cria_equipe_time_e_jogo_em_tres_chamadas_e_o_jogo_fica_operavel(): void
    {
        // 1) Time da casa — sem equipe_id, cria a equipe junto.
        $respostaCasa = $this->postJson('/api/placar/times', [
            'equipe_nome' => 'Equipe Avulsa Casa',
            'modalidade' => 'futsal',
        ])->assertCreated();
        $timeCasaId = $respostaCasa->json('id');

        // 2) Time visitante — mesma coisa, outra equipe.
        $respostaFora = $this->postJson('/api/placar/times', [
            'equipe_nome' => 'Equipe Avulsa Fora',
            'modalidade' => 'futsal',
        ])->assertCreated();
        $timeForaId = $respostaFora->json('id');

        $this->assertDatabaseHas('equipes', ['nome' => 'Equipe Avulsa Casa', 'criado_em_campo' => true]);
        $this->assertDatabaseHas('times', ['id' => $timeCasaId, 'criado_em_campo' => true]);

        // 3) O jogo — as duas equipes e os dois times nasceram nas chamadas
        // acima, sem cadastro prévio nenhum pela tela web.
        $respostaJogo = $this->postJson('/api/placar/jogos', [
            'modalidade' => 'futsal',
            'time_casa_id' => $timeCasaId,
            'time_fora_id' => $timeForaId,
            'local' => 'Quadra improvisada',
        ])->assertCreated();

        $jogoId = $respostaJogo->json('jogo.id');
        $this->assertDatabaseHas('jogos', ['id' => $jogoId, 'status' => Jogo::STATUS_AGENDADO, 'criado_em_campo' => true]);

        // "Operável": dá para seguir direto para o ciclo de vida, sem mais
        // nenhum cadastro prévio.
        $this->postJson("/api/placar/jogos/{$jogoId}/iniciar")
            ->assertOk()
            ->assertJson(['status' => Jogo::STATUS_AO_VIVO]);
    }

    public function test_recusa_criar_jogo_com_time_de_modalidade_diferente(): void
    {
        $futsalTime = $this->postJson('/api/placar/times', [
            'equipe_nome' => 'Equipe Futsal', 'modalidade' => 'futsal',
        ])->assertCreated()->json('id');

        $basqueteTime = $this->postJson('/api/placar/times', [
            'equipe_nome' => 'Equipe Basquete', 'modalidade' => 'basquete',
        ])->assertCreated()->json('id');

        $this->postJson('/api/placar/jogos', [
            'modalidade' => 'futsal',
            'time_casa_id' => $futsalTime,
            'time_fora_id' => $basqueteTime,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('time_fora_id');

        $this->assertSame(0, Jogo::count());
    }

    public function test_reenviar_a_mesma_criacao_de_time_nao_duplica(): void
    {
        $primeira = $this->postJson('/api/placar/times', [
            'equipe_nome' => 'Equipe Repetida', 'modalidade' => 'futsal',
        ])->assertCreated();

        // O time já existe da chamada anterior — reenviar não cria de novo,
        // então a resposta é 200 (não 201: nada "wasRecentlyCreated" aqui).
        $segunda = $this->postJson('/api/placar/times', [
            'equipe_nome' => 'Equipe Repetida', 'modalidade' => 'futsal',
        ])->assertOk();

        $this->assertSame($primeira->json('id'), $segunda->json('id'));
        $this->assertSame(1, \App\Models\Placar\Equipe::where('nome', 'Equipe Repetida')->count());
    }
}
