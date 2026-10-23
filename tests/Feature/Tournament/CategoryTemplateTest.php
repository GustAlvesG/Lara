<?php

namespace Tests\Feature\Tournament;

use App\Models\Tournament\TournamentCategoryTemplate as Template;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesTournamentSchema;
use Tests\TestCase;

/**
 * Torneios, F2: a migration do módulo e o catálogo de categorias de cada
 * grupo de espaços (Torneios › Categorias, permissão `torneios.gerenciar`).
 */
class CategoryTemplateTest extends TestCase
{
    use CreatesTournamentSchema;

    private User $esporte;
    private int $beach;
    private int $tenis;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTournamentSchema();

        // TI tem acesso total: alcança `torneios.gerenciar` sem configurar setor.
        $this->esporte = $this->makeUser('Pessoa do Esporte');
        $this->joinSector($this->esporte, 'TI');

        $this->beach = DB::table('place_groups')->insertGetId(['name' => 'Beach Tennis', 'category' => 'esportiva']);
        $this->tenis = DB::table('place_groups')->insertGetId(['name' => 'Tênis', 'category' => null]);
        DB::table('place_groups')->insert(['name' => 'Churrasqueiras', 'category' => 'social']);
    }

    private function template(int $group, string $name, array $attributes = []): Template
    {
        return Template::create(array_merge([
            'place_group_id' => $group,
            'name' => $name,
            'team_size' => 2,
            'gender' => Template::GENDER_ANY,
        ], $attributes));
    }

    public function test_a_migration_cria_todas_as_tabelas_e_pode_rodar_de_novo(): void
    {
        foreach ([
            'tournament_category_templates', 'tournaments', 'tournament_categories', 'tournament_payments',
            'tournament_registrations', 'tournament_teams', 'tournament_team_members', 'tournament_team_invites',
            'tournament_stages', 'tournament_groups', 'tournament_group_teams', 'tournament_matches',
            'tournament_match_sets', 'tournament_match_result_logs',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "faltou {$table}");
        }

        // Deploy que falhou no meio roda de novo sem erro.
        (require database_path('migrations/' . self::TOURNAMENT_MIGRATION))->up();
        $this->assertTrue(Schema::hasTable('tournament_match_result_logs'));
    }

    public function test_so_quem_gerencia_torneios_abre_o_catalogo(): void
    {
        $this->actingAs($this->makeUser('Sem Acesso'))->get(route('tournaments.categories.index'))->assertForbidden();

        $this->actingAs($this->esporte)->get(route('tournaments.categories.index'))
            ->assertOk()
            ->assertSee('Categorias de torneio');
    }

    public function test_lista_so_grupos_esportivos_e_as_categorias_do_grupo_escolhido(): void
    {
        $this->template($this->beach, 'Feminina B');
        $this->template($this->tenis, 'Simples 40+', ['team_size' => 1]);

        $this->actingAs($this->esporte)->get(route('tournaments.categories.index', ['group' => $this->beach]))
            ->assertOk()
            ->assertSee('Beach Tennis')
            ->assertDontSee('Churrasqueiras')
            ->assertSee('Feminina B')
            ->assertDontSee('Simples 40+');
    }

    public function test_cadastra_categoria_no_grupo(): void
    {
        $this->actingAs($this->esporte)->post(route('tournaments.categories.store'), [
            'place_group_id' => $this->beach,
            'name' => 'Mista 40+',
            'team_size' => 2,
            'gender' => Template::GENDER_ANY,
            'min_age' => 40,
            'active' => '1',
        ])->assertRedirect(route('tournaments.categories.index', ['group' => $this->beach]));

        $template = Template::where('name', 'Mista 40+')->firstOrFail();
        $this->assertSame($this->beach, $template->place_group_id);
        $this->assertSame(40, $template->min_age);
        $this->assertNull($template->max_age);
        $this->assertTrue($template->active);
    }

    public function test_nome_repetido_so_e_recusado_no_mesmo_grupo(): void
    {
        $this->template($this->beach, 'Masculina A');
        $data = ['name' => 'Masculina A', 'team_size' => 2, 'gender' => Template::GENDER_MALE];

        $this->actingAs($this->esporte)
            ->post(route('tournaments.categories.store'), $data + ['place_group_id' => $this->beach])
            ->assertSessionHasErrors('name');

        $this->actingAs($this->esporte)
            ->post(route('tournaments.categories.store'), $data + ['place_group_id' => $this->tenis])
            ->assertSessionHasNoErrors();
    }

    public function test_idade_maxima_nao_pode_ser_menor_que_a_minima(): void
    {
        $this->actingAs($this->esporte)->post(route('tournaments.categories.store'), [
            'place_group_id' => $this->beach,
            'name' => 'Errada',
            'team_size' => 2,
            'gender' => Template::GENDER_ANY,
            'min_age' => 40,
            'max_age' => 30,
        ])->assertSessionHasErrors('max_age');
    }

    public function test_edita_sem_trocar_de_grupo_e_remove_do_catalogo(): void
    {
        $template = $this->template($this->beach, 'Feminina C');

        $this->actingAs($this->esporte)->put(route('tournaments.categories.update', $template), [
            'place_group_id' => $this->tenis,
            'name' => 'Feminina C',
            'team_size' => 2,
            'gender' => Template::GENDER_FEMALE,
            'max_age' => 17,
        ])->assertSessionHasNoErrors();

        $template->refresh();
        $this->assertSame($this->beach, $template->place_group_id);
        $this->assertSame(Template::GENDER_FEMALE, $template->gender);
        $this->assertSame(17, $template->max_age);
        $this->assertFalse($template->active);

        $this->actingAs($this->esporte)->delete(route('tournaments.categories.destroy', $template))->assertRedirect();
        $this->assertSoftDeleted('tournament_category_templates', ['id' => $template->id]);

        // O nome fica livre de novo no grupo.
        $this->actingAs($this->esporte)->post(route('tournaments.categories.store'), [
            'place_group_id' => $this->beach, 'name' => 'Feminina C', 'team_size' => 2, 'gender' => Template::GENDER_FEMALE,
        ])->assertSessionHasNoErrors();
    }
}
