<?php

namespace Tests\Feature\Placar\Web;

use App\Models\Placar\Equipe;
use App\Models\Placar\Modalidade;
use App\Models\Placar\Time;
use Tests\Concerns\MigratesPlacarSchema;
use Tests\Concerns\MocksPlacarUser;
use Tests\TestCase;

/**
 * Os formulários de criação do Placar têm dois botões (ver
 * RedirecionaAposCadastro):
 *
 * - "Cadastrar" volta para a lista. Antes abria a ficha, que é um
 *   formulário de edição: quem acabava de cadastrar caía editando.
 * - "Cadastrar e criar outro" reabre o formulário com só os campos de
 *   contexto preenchidos (equipe, modalidade, categoria, data…), nunca o
 *   nome nem os times do registro que acabou de ser criado.
 */
class CadastroEmSequenciaTest extends TestCase
{
    use MigratesPlacarSchema;
    use MocksPlacarUser;

    private Modalidade $volei;
    private Equipe $equipe;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migratePlacarSchema();

        $this->volei = Modalidade::create(['nome' => 'Vôlei', 'slug' => 'volei', 'ativo' => true]);
        $this->equipe = Equipe::create(['nome' => 'Clube dos Funcionários', 'nome_curto' => 'CF', 'ativo' => true]);
    }

    private function dadosDoTime(array $extra = []): array
    {
        return [
            'equipe_id' => $this->equipe->id,
            'modalidade_id' => $this->volei->id,
            'categoria' => 'Sub-15',
            'nome_exibicao' => 'Funcionários',
            ...$extra,
        ];
    }

    public function test_cadastrar_volta_para_a_lista_com_atalho_para_o_elenco(): void
    {
        $resposta = $this->actingAs($this->usuarioDoSetorEsporte())
            ->post(route('placar.times.store'), $this->dadosDoTime(['acao' => 'salvar']));

        $time = Time::sole();

        $resposta->assertRedirect(route('placar.times.index'))
            ->assertSessionHas('success')
            ->assertSessionHas('success_link.url', route('placar.times.show', $time));
    }

    /** Sem `acao` (ex.: Enter num navegador que não envia o botão) é o cadastro simples. */
    public function test_sem_acao_tambem_volta_para_a_lista(): void
    {
        $this->actingAs($this->usuarioDoSetorEsporte())
            ->post(route('placar.times.store'), $this->dadosDoTime())
            ->assertRedirect(route('placar.times.index'));
    }

    public function test_criar_outro_reabre_o_formulario_mantendo_so_o_contexto(): void
    {
        $this->actingAs($this->usuarioDoSetorEsporte())
            ->post(route('placar.times.store'), $this->dadosDoTime(['acao' => 'continuar']))
            ->assertRedirect(route('placar.times.create'))
            ->assertSessionHas('success')
            ->assertSessionHasInput('equipe_id', $this->equipe->id)
            ->assertSessionHasInput('modalidade_id', $this->volei->id)
            ->assertSessionHasInput('categoria', 'Sub-15')
            ->assertSessionMissing('_old_input.nome_exibicao');

        $this->assertSame(1, Time::count());
    }

    /** Equipe não tem contexto a manter: o formulário volta vazio. */
    public function test_criar_outra_equipe_nao_repete_o_nome(): void
    {
        $this->actingAs($this->usuarioDoSetorEsporte())
            ->post(route('placar.equipes.store'), ['nome' => 'Atibaia Esporte Clube', 'acao' => 'continuar'])
            ->assertRedirect(route('placar.equipes.create'))
            ->assertSessionMissing('_old_input');
    }

    public function test_equipe_cadastrada_oferece_criar_o_time_dela(): void
    {
        $this->actingAs($this->usuarioDoSetorEsporte())
            ->post(route('placar.equipes.store'), ['nome' => 'Atibaia Esporte Clube'])
            ->assertRedirect(route('placar.equipes.index'));

        $nova = Equipe::where('nome', 'Atibaia Esporte Clube')->sole();

        $this->assertSame(
            route('placar.times.create', ['equipe_id' => $nova->id]),
            session('success_link.url'),
        );
    }

    /** O atalho "criar um time desta equipe" chega com a equipe escolhida. */
    public function test_novo_time_aceita_a_equipe_pela_url(): void
    {
        $this->actingAs($this->usuarioDoSetorEsporte())
            ->get(route('placar.times.create', ['equipe_id' => $this->equipe->id]))
            ->assertOk()
            ->assertSee('<option value="' . $this->equipe->id . '" selected', false);
    }

    public function test_criar_outro_jogo_mantem_a_rodada_e_nao_os_times(): void
    {
        $casa = Time::create([
            'equipe_id' => $this->equipe->id, 'modalidade_id' => $this->volei->id,
            'categoria' => 'Sub-15', 'ativo' => true,
        ]);
        $outra = Equipe::create(['nome' => 'Atibaia Esporte Clube', 'ativo' => true]);
        $fora = Time::create([
            'equipe_id' => $outra->id, 'modalidade_id' => $this->volei->id,
            'categoria' => 'Sub-15', 'ativo' => true,
        ]);

        $this->actingAs($this->usuarioDoSetorEsporte())
            ->post(route('placar.jogos.store'), [
                'modalidade_id' => $this->volei->id,
                'categoria' => 'sub15',
                'time_casa_id' => $casa->id,
                'time_fora_id' => $fora->id,
                'data_hora' => '2026-10-20T19:00',
                'local' => 'Ginásio',
                'acao' => 'continuar',
            ])
            ->assertRedirect(route('placar.jogos.create'))
            ->assertSessionHasInput('categoria', 'sub15')
            ->assertSessionHasInput('data_hora', '2026-10-20T19:00')
            ->assertSessionHasInput('local', 'Ginásio')
            ->assertSessionMissing('_old_input.time_casa_id')
            ->assertSessionMissing('_old_input.time_fora_id');
    }
}
