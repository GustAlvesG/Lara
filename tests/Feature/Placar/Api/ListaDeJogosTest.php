<?php

namespace Tests\Feature\Placar\Api;

use App\Models\Placar\ApiCliente;
use App\Models\Placar\Equipe;
use App\Models\Placar\Jogo;
use App\Models\Placar\Modalidade;
use App\Models\Placar\Time;
use App\Support\Placar\PlacarAbilities;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MigratesPlacarSchema;
use Tests\TestCase;

/**
 * GET /placar/jogos — a tela de seleção do Node.
 *
 * Cada time vem com equipe e categoria além do nome de exibição: dois
 * times da mesma equipe só se distinguem por elas, e o operador precisa
 * acertar o jogo antes de começar.
 */
class ListaDeJogosTest extends TestCase
{
    use MigratesPlacarSchema;

    private Equipe $equipe;
    private Modalidade $futsal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migratePlacarSchema();

        Sanctum::actingAs(ApiCliente::create(['nome' => 'node', 'ativo' => true]), [PlacarAbilities::OPERAR]);

        $this->futsal = Modalidade::create(['nome' => 'Futsal', 'slug' => 'futsal', 'ativo' => true]);
        $this->equipe = Equipe::create(['nome' => 'Clube dos Funcionários', 'nome_curto' => 'CF', 'ativo' => true]);
    }

    private function time(Equipe $equipe, string $categoria): Time
    {
        return Time::create([
            'equipe_id' => $equipe->id, 'modalidade_id' => $this->futsal->id,
            'categoria' => $categoria, 'ativo' => true,
        ]);
    }

    private function jogo(Time $casa, Time $fora, ?string $quando = null): Jogo
    {
        return Jogo::create([
            'modalidade_id' => $this->futsal->id,
            'time_casa_id' => $casa->id, 'time_fora_id' => $fora->id,
            'data_hora' => $quando ? \Illuminate\Support\Carbon::parse($quando) : now(),
            'status' => Jogo::STATUS_AGENDADO, 'criado_em_campo' => false,
        ]);
    }

    public function test_cada_time_traz_equipe_e_categoria(): void
    {
        $visitante = Equipe::create(['nome' => 'Associação Vila Nova', 'nome_curto' => 'Vila Nova', 'ativo' => true]);
        $this->jogo($this->time($this->equipe, 'Adulto'), $this->time($visitante, 'Adulto'));

        $item = $this->getJson('/api/placar/jogos')->assertOk()->json('data.0');

        $this->assertSame('Adulto', $item['time_casa']['categoria']);
        $this->assertSame('Clube dos Funcionários', $item['time_casa']['equipe']['nome']);
        $this->assertSame('CF', $item['time_casa']['equipe']['nome_curto']);
        $this->assertSame('Associação Vila Nova', $item['time_fora']['equipe']['nome']);
    }

    /**
     * É o caso que motiva o campo: dois jogos da MESMA equipe no mesmo dia,
     * separados só pela categoria.
     */
    public function test_dois_jogos_da_mesma_equipe_se_distinguem_pela_categoria(): void
    {
        $adversario = Equipe::create(['nome' => 'Fortaleza', 'ativo' => true]);

        $this->jogo($this->time($this->equipe, 'Adulto'), $this->time($adversario, 'Adulto'));
        $this->jogo($this->time($this->equipe, 'Sub-15'), $this->time($adversario, 'Sub-15'));

        $categorias = collect($this->getJson('/api/placar/jogos')->assertOk()->json('data'))
            ->pluck('time_casa.categoria')
            ->sort()
            ->values()
            ->all();

        $this->assertSame(['Adulto', 'Sub-15'], $categorias);
    }

    /** O detalhe do jogo (gameState do Node) traz a mesma identificação. */
    public function test_o_detalhe_do_jogo_tambem_traz_equipe_e_categoria(): void
    {
        $adversario = Equipe::create(['nome' => 'Fortaleza', 'nome_curto' => 'FOR', 'ativo' => true]);
        $jogo = $this->jogo($this->time($this->equipe, 'Sub-15'), $this->time($adversario, 'Sub-15'));

        $this->getJson("/api/placar/jogos/{$jogo->id}")
            ->assertOk()
            ->assertJsonPath('time_casa.categoria', 'Sub-15')
            ->assertJsonPath('time_casa.equipe.nome_curto', 'CF')
            ->assertJsonPath('time_fora.equipe.nome', 'Fortaleza');
    }

    public function test_filtra_por_data_e_status(): void
    {
        $adversario = Equipe::create(['nome' => 'Fortaleza', 'ativo' => true]);
        $casa = $this->time($this->equipe, 'Adulto');
        $fora = $this->time($adversario, 'Adulto');

        $deOntem = $this->jogo($casa, $fora, now()->subDay()->setTime(19, 0)->toDateTimeString());
        $deOntem->update(['status' => Jogo::STATUS_ENCERRADO]);
        $this->jogo($casa, $fora, now()->setTime(20, 0)->toDateTimeString());

        $ontem = now()->subDay()->toDateString();

        $resposta = $this->getJson("/api/placar/jogos?data={$ontem}&status=encerrado")->assertOk();

        $resposta->assertJsonCount(1, 'data');
        $resposta->assertJsonPath('data.0.id', $deOntem->id);
    }
}
