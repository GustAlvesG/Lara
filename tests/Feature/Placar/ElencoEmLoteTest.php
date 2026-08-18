<?php

namespace Tests\Feature\Placar;

use App\Models\Placar\Elenco;
use App\Models\Placar\Equipe;
use App\Models\Placar\Jogador;
use App\Models\Placar\Modalidade;
use App\Models\Placar\Time;
use Database\Seeders\ModalidadeSeeder;
use Tests\Concerns\MigratesPlacarSchema;
use Tests\Concerns\MocksPlacarUser;
use Tests\TestCase;

/**
 * Montar um elenco é marcar vários jogadores e salvar uma vez — não um
 * POST (e um reload) por jogador.
 *
 * O formato é `jogadores[{id}][selecionado|numero|posicao]`, o mesmo já
 * usado pela tela de escalação. Diferente da escalação, aqui a operação
 * ACRESCENTA: quem já está no elenco e não veio marcado continua onde
 * está.
 */
class ElencoEmLoteTest extends TestCase
{
    use MigratesPlacarSchema;
    use MocksPlacarUser;

    private Time $time;
    private Equipe $equipe;
    private Modalidade $futsal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migratePlacarSchema();
        (new ModalidadeSeeder())->run();

        $this->futsal = Modalidade::where('slug', 'futsal')->first();
        $this->equipe = Equipe::create(['nome' => 'Clube dos Funcionários', 'ativo' => true]);
        $this->time = Time::create([
            'equipe_id' => $this->equipe->id, 'modalidade_id' => $this->futsal->id,
            'categoria' => 'Adulto', 'ativo' => true,
        ]);
    }

    private function jogador(string $nome, ?Equipe $equipe = null, ?Modalidade $modalidade = null): Jogador
    {
        return Jogador::create([
            'equipe_id' => ($equipe ?? $this->equipe)->id,
            'modalidade_id' => ($modalidade ?? $this->futsal)->id,
            'nome' => $nome, 'ativo' => true,
        ]);
    }

    private function enviar(array $jogadores)
    {
        return $this->actingAs($this->usuarioDoSetorEsporte())
            ->post(route('placar.times.elenco.store', $this->time), ['jogadores' => $jogadores]);
    }

    public function test_adiciona_varios_jogadores_numa_unica_requisicao(): void
    {
        $um = $this->jogador('Jogador Um');
        $dois = $this->jogador('Jogador Dois');
        $tres = $this->jogador('Jogador Três');

        $this->enviar([
            $um->id => ['selecionado' => '1', 'numero' => '1', 'posicao' => 'Goleiro'],
            $dois->id => ['selecionado' => '1', 'numero' => '10'],
            $tres->id => ['selecionado' => '1'],
        ])->assertRedirect();

        $this->assertSame(3, Elenco::count());
        $this->assertDatabaseHas('elencos', ['jogador_id' => $um->id, 'numero' => '1', 'posicao' => 'Goleiro']);
        $this->assertDatabaseHas('elencos', ['jogador_id' => $dois->id, 'numero' => '10']);
        // Sem número informado grava null, não string vazia.
        $this->assertDatabaseHas('elencos', ['jogador_id' => $tres->id, 'numero' => null]);
    }

    /** Linha não marcada não entra, mesmo que o número tenha sido digitado. */
    public function test_ignora_quem_nao_foi_marcado(): void
    {
        $marcado = $this->jogador('Marcado');
        $naoMarcado = $this->jogador('Não Marcado');

        $this->enviar([
            $marcado->id => ['selecionado' => '1', 'numero' => '7'],
            $naoMarcado->id => ['numero' => '8'],
        ])->assertRedirect();

        $this->assertSame(1, Elenco::count());
        $this->assertDatabaseHas('elencos', ['jogador_id' => $marcado->id]);
        $this->assertDatabaseMissing('elencos', ['jogador_id' => $naoMarcado->id]);
    }

    /**
     * Um jogador de outra equipe no lote não pode derrubar os válidos — só
     * ele fica de fora, e o aviso diz quem foi.
     */
    public function test_recusa_apenas_o_inelegivel_e_mantem_os_demais(): void
    {
        $valido = $this->jogador('Jogador Válido');
        $outraEquipe = Equipe::create(['nome' => 'Vila Nova', 'ativo' => true]);
        $invalido = $this->jogador('De Outra Equipe', $outraEquipe);

        $this->enviar([
            $valido->id => ['selecionado' => '1'],
            $invalido->id => ['selecionado' => '1'],
        ])->assertRedirect()->assertSessionHas('warning');

        $this->assertSame(1, Elenco::count());
        $this->assertDatabaseHas('elencos', ['jogador_id' => $valido->id]);
        $this->assertDatabaseMissing('elencos', ['jogador_id' => $invalido->id]);
    }

    public function test_acrescenta_sem_remover_quem_ja_estava_no_elenco(): void
    {
        $antigo = $this->jogador('Já no Elenco');
        Elenco::create([
            'time_id' => $this->time->id, 'jogador_id' => $antigo->id,
            'temporada' => now()->year, 'numero' => '9', 'ativo' => true,
        ]);

        $novo = $this->jogador('Novo');

        $this->enviar([$novo->id => ['selecionado' => '1', 'numero' => '11']])->assertRedirect();

        $this->assertSame(2, Elenco::count());
        // O antigo continua, com o número que já tinha.
        $this->assertDatabaseHas('elencos', ['jogador_id' => $antigo->id, 'numero' => '9', 'ativo' => true]);
    }

    public function test_avisa_quando_o_jogador_ja_estava_no_elenco(): void
    {
        $jogador = $this->jogador('Repetido');
        Elenco::create([
            'time_id' => $this->time->id, 'jogador_id' => $jogador->id,
            'temporada' => now()->year, 'ativo' => true,
        ]);

        $this->enviar([$jogador->id => ['selecionado' => '1']])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(1, Elenco::count());
    }

    /** Reativar quem foi removido antes não pode criar uma segunda linha. */
    public function test_readiciona_jogador_removido_reaproveitando_o_vinculo(): void
    {
        $jogador = $this->jogador('Voltou');
        Elenco::create([
            'time_id' => $this->time->id, 'jogador_id' => $jogador->id,
            'temporada' => now()->year, 'numero' => '5', 'ativo' => false,
        ]);

        $this->enviar([$jogador->id => ['selecionado' => '1', 'numero' => '6']])->assertRedirect();

        $this->assertSame(1, Elenco::count());
        $this->assertDatabaseHas('elencos', ['jogador_id' => $jogador->id, 'numero' => '6', 'ativo' => true]);
    }

    public function test_envio_sem_nenhum_marcado_nao_grava_nada(): void
    {
        $jogador = $this->jogador('Ninguém Marcado');

        $this->enviar([$jogador->id => ['numero' => '3']])
            ->assertRedirect()
            ->assertSessionHas('warning');

        $this->assertSame(0, Elenco::count());
    }

    /**
     * A tela precisa entregar a seleção múltipla e a lista de elegíveis —
     * é por ela que o elenco é montado.
     */
    public function test_a_ficha_do_time_traz_a_selecao_multipla(): void
    {
        $this->jogador('Disponível Um');
        $this->jogador('Disponível Dois');

        $this->actingAs($this->usuarioDoSetorEsporte())
            ->get(route('placar.times.show', $this->time))
            ->assertOk()
            ->assertSee('Marcar todos')
            ->assertSee('Adicionar ao elenco')
            ->assertSee('Disponível Um')
            ->assertSee('Disponível Dois');
    }
}
