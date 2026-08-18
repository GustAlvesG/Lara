<?php

namespace Tests\Feature\Placar;

use App\Models\Placar\ApiCliente;
use App\Models\Placar\Elenco;
use App\Models\Placar\Equipe;
use App\Models\Placar\Jogador;
use App\Models\Placar\Modalidade;
use App\Models\Placar\Time;
use App\Support\Placar\PlacarAbilities;
use Database\Seeders\ModalidadeSeeder;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MigratesPlacarSchema;
use Tests\Concerns\MocksPlacarUser;
use Tests\TestCase;

/**
 * O cadastro do jogador guarda como dado pessoal apenas nome e data de
 * nascimento — documento saiu.
 *
 * A data de nascimento é usada como IDADE na hora de montar o elenco: quem
 * escala precisa saber se o jogador cabe na categoria do time (Sub-15,
 * Sub-17…), e conferir isso de cabeça a partir da data é onde o erro
 * acontece.
 */
class IdadeJogadorTest extends TestCase
{
    use MigratesPlacarSchema;
    use MocksPlacarUser;

    private Equipe $equipe;
    private Modalidade $futsal;
    private Time $time;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migratePlacarSchema();
        (new ModalidadeSeeder())->run();

        $this->futsal = Modalidade::where('slug', 'futsal')->first();
        $this->equipe = Equipe::create(['nome' => 'Clube dos Funcionários', 'ativo' => true]);
        $this->time = Time::create([
            'equipe_id' => $this->equipe->id, 'modalidade_id' => $this->futsal->id,
            'categoria' => 'Sub-15', 'ativo' => true,
        ]);
    }

    private function jogador(string $nome, ?string $nascimento): Jogador
    {
        return Jogador::create([
            'equipe_id' => $this->equipe->id, 'modalidade_id' => $this->futsal->id,
            'nome' => $nome, 'data_nascimento' => $nascimento, 'ativo' => true,
        ]);
    }

    public function test_idade_e_em_anos_completos(): void
    {
        $jogador = $this->jogador('Aniversário Feito', now()->subYears(17)->subMonths(2)->toDateString());

        $this->assertSame(17, $jogador->idade());
    }

    /** Quem faz aniversário amanhã ainda não fez os anos — é o caso que separa Sub-15 de Sub-17. */
    public function test_idade_nao_conta_aniversario_que_ainda_nao_chegou(): void
    {
        $jogador = $this->jogador('Faz Amanhã', now()->subYears(15)->addDay()->toDateString());

        $this->assertSame(14, $jogador->idade());
    }

    public function test_sem_data_de_nascimento_a_idade_e_nula(): void
    {
        $this->assertNull($this->jogador('Sem Data', null)->idade());
    }

    /** É a tela onde o jogador é vinculado ao time. */
    public function test_a_tela_de_montar_elenco_mostra_a_idade_dos_disponiveis(): void
    {
        $this->jogador('Com Idade', now()->subYears(14)->subMonth()->toDateString());
        $this->jogador('Sem Idade', null);

        $this->actingAs($this->usuarioDoSetorEsporte())
            ->get(route('placar.times.show', $this->time))
            ->assertOk()
            ->assertSee('14 anos')
            ->assertSee('idade não informada');
    }

    public function test_a_tela_mostra_a_idade_de_quem_ja_esta_no_elenco(): void
    {
        $jogador = $this->jogador('Já Vinculado', now()->subYears(13)->subMonth()->toDateString());
        Elenco::create([
            'time_id' => $this->time->id, 'jogador_id' => $jogador->id,
            'temporada' => now()->year, 'numero' => '9', 'ativo' => true,
        ]);

        $this->actingAs($this->usuarioDoSetorEsporte())
            ->get(route('placar.times.show', $this->time))
            ->assertOk()
            ->assertSee('13 anos');
    }

    public function test_a_coluna_documento_nao_existe_mais(): void
    {
        $this->assertFalse(Schema::hasColumn('jogadores', 'documento'));
    }

    /** Cadastro antigo mandando `documento` não pode ressuscitar o campo. */
    public function test_o_cadastro_web_ignora_documento_enviado(): void
    {
        $this->actingAs($this->usuarioDoSetorEsporte())
            ->post(route('placar.jogadores.store'), [
                'nome' => 'Sem Documento',
                'equipe_id' => $this->equipe->id,
                'modalidade_id' => $this->futsal->id,
                'documento' => 'MG123456',
            ])
            ->assertRedirect();

        $jogador = Jogador::where('nome', 'Sem Documento')->first();
        $this->assertNotNull($jogador);
        $this->assertArrayNotHasKey('documento', $jogador->getAttributes());
    }

    /** O telão recebe a idade pronta e não recebe mais o documento. */
    public function test_a_api_devolve_idade_no_lugar_do_documento(): void
    {
        Sanctum::actingAs(ApiCliente::create(['nome' => 'node', 'ativo' => true]), [PlacarAbilities::OPERAR]);

        $jogador = $this->jogador('Do Telão', now()->subYears(21)->subMonth()->toDateString());

        $this->deleteJson("/api/placar/jogadores/{$jogador->id}/foto")
            ->assertOk()
            ->assertJsonPath('idade', 21)
            ->assertJsonMissingPath('documento');
    }
}
