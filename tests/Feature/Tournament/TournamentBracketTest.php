<?php

namespace Tests\Feature\Tournament;

use App\Exceptions\ConfirmationRequired;
use App\Exceptions\TournamentException;
use App\Models\Tournament\Tournament;
use App\Models\Tournament\TournamentCategory;
use App\Models\Tournament\TournamentMatch as Game;
use App\Models\Tournament\TournamentStage as Stage;
use App\Models\Tournament\TournamentTeam as Team;
use App\Models\User;
use App\Services\Tournament\TournamentBracketService;
use App\Services\Tournament\TournamentSetupService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsTournaments;
use Tests\Concerns\CreatesTournamentSchema;
use Tests\TestCase;

/**
 * Torneios, F5: chaveamento. Eliminatória (passa direto, cabeças de chave,
 * 3º lugar, montagem à mão), grupos + mata-mata (serpentina, todos contra
 * todos, cruzado), gerar de novo só antes do primeiro resultado, divulgar no
 * site, a tela e a API.
 */
class TournamentBracketTest extends TestCase
{
    use CreatesTournamentSchema;
    use BuildsTournaments;

    private TournamentBracketService $brackets;
    private Tournament $tournament;
    private User $organizer;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['services.api.token' => 'token-de-teste']);

        $this->createTournamentSchema();
        $this->fakeIntegrations();
        $this->brackets = $this->app->make(TournamentBracketService::class);
        $this->tournament = $this->tournament();
        $this->organizer = $this->makeUser('Pessoa do Esporte');
        $this->joinSector($this->organizer, 'TI');
    }

    /** Times prontos na categoria: "Time 1", "Time 2"…; `$seeds` = [índice => cabeça]. */
    private function teams(TournamentCategory $category, int $count, array $seeds = [], string $status = Team::STATUS_COMPLETE): array
    {
        $teams = [];

        for ($i = 1; $i <= $count; $i++) {
            $teams[$i] = Team::create([
                'tournament_category_id' => $category->id,
                'name' => "Time {$i}",
                'origin' => Team::ORIGIN_ORGANIZER,
                'status' => $status,
                'seed' => $seeds[$i] ?? null,
            ]);
        }

        return $teams;
    }

    private function knockout(TournamentCategory $category): Stage
    {
        return $category->stages()->where('type', Stage::TYPE_KNOCKOUT)->firstOrFail();
    }

    private function finish(Game $match, int $winnerId): void
    {
        $match->update([
            'status' => Game::STATUS_FINISHED,
            'winner_team_id' => $winnerId,
            'home_sets' => $winnerId === $match->home_team_id ? 2 : 0,
            'away_sets' => $winnerId === $match->away_team_id ? 2 : 0,
        ]);
        $this->brackets->advance($match->fresh());
    }

    public function test_eliminatoria_com_passa_direto_para_os_cabecas_de_chave(): void
    {
        $category = $this->category($this->tournament);
        $teams = $this->teams($category, 5, seeds: [4 => 1, 5 => 2]);

        $this->brackets->generate($category, Stage::GENERATION_DRAW, $this->organizer);

        $stage = $this->knockout($category);
        $this->assertSame(1, $category->stages()->count());
        $this->assertSame(7, $stage->matches()->count()); // chave de 8: 4 + 2 + 1

        $roundOne = $stage->matches()->where('round', 1)->orderBy('position')->get();
        $this->assertSame(3, $roundOne->where('status', Game::STATUS_BYE)->count());

        // Cabeça 1 no jogo 1, cabeça 2 no jogo 3: passam direto, em metades opostas.
        $this->assertSame($teams[4]->id, $roundOne[0]->home_team_id);
        $this->assertSame(Game::STATUS_BYE, $roundOne[0]->status);
        $this->assertSame($teams[5]->id, $roundOne[2]->home_team_id);
        $this->assertSame(Game::STATUS_BYE, $roundOne[2]->status);

        $semis = $stage->matches()->where('round', 2)->orderBy('position')->get();
        $this->assertSame($teams[4]->id, $semis[0]->home_team_id);
        $this->assertSame($teams[5]->id, $semis[1]->home_team_id);

        // Cada time aparece uma vez na 1ª rodada.
        $placed = $roundOne->flatMap(fn ($m) => [$m->home_team_id, $m->away_team_id])->filter()->sort()->values()->all();
        $this->assertSame(collect($teams)->pluck('id')->sort()->values()->all(), $placed);

        // O vencedor segue para o jogo certo.
        $this->assertSame($semis[0]->id, $roundOne[1]->next_match_id);
        $this->assertSame(Game::SLOT_AWAY, $roundOne[1]->next_slot);
    }

    public function test_terceiro_lugar_recebe_os_perdedores_das_semifinais(): void
    {
        $category = $this->category($this->tournament, ['third_place_match' => true]);
        $teams = $this->teams($category, 4, seeds: [1 => 1, 2 => 2, 3 => 3, 4 => 4]);

        $this->brackets->generate($category, Stage::GENERATION_DRAW, $this->organizer);

        $stage = $this->knockout($category);
        $this->assertSame(4, $stage->matches()->count());
        $third = $stage->matches()->where('third_place', true)->firstOrFail();
        $final = $stage->matches()->where('round', 2)->where('third_place', false)->firstOrFail();

        // 1 × 4 e 2 × 3.
        [$semi1, $semi2] = $stage->matches()->where('round', 1)->orderBy('position')->get()->all();
        $this->assertSame([$teams[1]->id, $teams[4]->id], [$semi1->home_team_id, $semi1->away_team_id]);
        $this->assertSame([$teams[2]->id, $teams[3]->id], [$semi2->home_team_id, $semi2->away_team_id]);

        $this->finish($semi1, $teams[4]->id);
        $this->finish($semi2, $teams[2]->id);

        $final->refresh();
        $third->refresh();
        $this->assertSame([$teams[4]->id, $teams[2]->id], [$final->home_team_id, $final->away_team_id]);
        $this->assertSame([$teams[1]->id, $teams[3]->id], [$third->home_team_id, $third->away_team_id]);
    }

    public function test_sem_terceiro_lugar_com_tres_times(): void
    {
        $category = $this->category($this->tournament, ['third_place_match' => true]);
        $this->teams($category, 3);

        $this->brackets->generate($category, Stage::GENERATION_DRAW, $this->organizer);

        $this->assertSame(0, $this->knockout($category)->matches()->where('third_place', true)->count());
    }

    public function test_entram_os_em_formacao_e_os_desfeitos_nao(): void
    {
        $category = $this->category($this->tournament);
        $this->teams($category, 2);
        $forming = Team::create(['tournament_category_id' => $category->id, 'name' => 'Faltando um', 'origin' => Team::ORIGIN_ORGANIZER, 'status' => Team::STATUS_FORMING]);
        $gone = Team::create(['tournament_category_id' => $category->id, 'name' => 'Desfeito', 'origin' => Team::ORIGIN_ORGANIZER, 'status' => Team::STATUS_CANCELLED]);

        $this->brackets->generate($category, Stage::GENERATION_DRAW, $this->organizer);

        $ids = Game::where('round', 1)->get()->flatMap(fn ($m) => [$m->home_team_id, $m->away_team_id])->filter();
        $this->assertTrue($ids->contains($forming->id));
        $this->assertFalse($ids->contains($gone->id));
        $this->assertCount(0, $this->brackets->outdated($category)['missing']);

        // Time novo depois da geração aparece como fora da chave.
        Team::create(['tournament_category_id' => $category->id, 'name' => 'Chegou depois', 'origin' => Team::ORIGIN_ORGANIZER, 'status' => Team::STATUS_COMPLETE]);
        $this->assertSame(['Chegou depois'], $this->brackets->outdated($category)['missing']->pluck('name')->all());
    }

    public function test_montagem_a_mao_e_suas_recusas(): void
    {
        $category = $this->category($this->tournament);
        $t = $this->teams($category, 3);

        $this->brackets->generate($category, Stage::GENERATION_MANUAL, $this->organizer, ['slots' => [$t[3]->id, null, $t[1]->id, $t[2]->id]]);

        $roundOne = $this->knockout($category)->matches()->where('round', 1)->orderBy('position')->get();
        $this->assertSame(Game::STATUS_BYE, $roundOne[0]->status);
        $this->assertSame($t[3]->id, $roundOne[0]->winner_team_id);
        $this->assertSame([$t[1]->id, $t[2]->id], [$roundOne[1]->home_team_id, $roundOne[1]->away_team_id]);
        $this->assertSame(Stage::GENERATION_MANUAL, $this->knockout($category)->generation);

        $refusals = [
            [$t[1]->id, $t[1]->id, $t[2]->id, $t[3]->id],  // repetido
            [$t[1]->id, null, $t[2]->id, null],            // faltou o 3
            [null, null, $t[1]->id, $t[2]->id],             // jogo sem ninguém
        ];

        foreach ($refusals as $slots) {
            try {
                $this->brackets->generate($category, Stage::GENERATION_MANUAL, $this->organizer, ['slots' => $slots]);
                $this->fail('Montagem inválida deveria ser recusada: ' . json_encode($slots));
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('slots', $e->errors());
            }
        }
    }

    public function test_grupos_em_serpentina_todos_contra_todos_e_cruzado(): void
    {
        $category = $this->category($this->tournament, [
            'format' => TournamentCategory::FORMAT_GROUPS_KNOCKOUT,
            'groups_count' => 2,
            'qualifiers_per_group' => 2,
        ]);
        $teams = $this->teams($category, 8, seeds: [7 => 1, 8 => 2]);

        $this->brackets->generate($category, Stage::GENERATION_DRAW, $this->organizer);

        $groupStage = $category->stages()->where('type', Stage::TYPE_GROUP)->firstOrFail();
        [$a, $b] = $groupStage->groups()->get()->all();
        $this->assertSame(['Grupo A', 'Grupo B'], [$a->name, $b->name]);
        $this->assertSame(4, $a->entries()->count());
        $this->assertSame(4, $b->entries()->count());

        // Cabeças em grupos diferentes.
        $this->assertTrue($a->entries()->where('tournament_team_id', $teams[7]->id)->exists());
        $this->assertTrue($b->entries()->where('tournament_team_id', $teams[8]->id)->exists());

        // 4 times: 6 jogos em 3 rodadas, cada time joga uma vez por rodada.
        $this->assertSame(6, $a->matches()->count());
        foreach ($a->matches()->get()->groupBy('round') as $games) {
            $ids = $games->flatMap(fn ($m) => [$m->home_team_id, $m->away_team_id]);
            $this->assertSame($ids->count(), $ids->unique()->count());
        }

        // Mata-mata: 1A × 2B, 1B × 2A, final.
        $knockout = $category->stages()->where('type', Stage::TYPE_KNOCKOUT)->firstOrFail();
        $this->assertSame(2, $knockout->order);
        [$m1, $m2] = $knockout->matches()->where('round', 1)->orderBy('position')->get()->all();
        $this->assertSame([$a->id, 1, $b->id, 2], [$m1->home_source_group_id, $m1->home_source_position, $m1->away_source_group_id, $m1->away_source_position]);
        $this->assertSame([$b->id, 1, $a->id, 2], [$m2->home_source_group_id, $m2->home_source_position, $m2->away_source_group_id, $m2->away_source_position]);
        $this->assertNull($m1->home_team_id);
        $this->assertSame(3, $knockout->matches()->count());
    }

    public function test_grupo_pequeno_demais_e_recusado(): void
    {
        $category = $this->category($this->tournament, [
            'format' => TournamentCategory::FORMAT_GROUPS_KNOCKOUT,
            'groups_count' => 2,
            'qualifiers_per_group' => 2,
        ]);
        $this->teams($category, 3);

        $this->expectException(ValidationException::class);
        $this->brackets->generate($category, Stage::GENERATION_DRAW, $this->organizer);
    }

    public function test_grupos_a_mao(): void
    {
        $category = $this->category($this->tournament, [
            'format' => TournamentCategory::FORMAT_GROUPS_KNOCKOUT,
            'groups_count' => 2,
            'qualifiers_per_group' => 1,
        ]);
        $t = $this->teams($category, 4);

        $this->brackets->generate($category, Stage::GENERATION_MANUAL, $this->organizer, ['groups' => [
            $t[1]->id => 1, $t[2]->id => 1, $t[3]->id => 0, $t[4]->id => 0,
        ]]);

        [$a, $b] = $category->stages()->where('type', Stage::TYPE_GROUP)->firstOrFail()->groups()->get()->all();
        $this->assertEqualsCanonicalizing([$t[3]->id, $t[4]->id], $a->entries()->pluck('tournament_team_id')->all());
        $this->assertEqualsCanonicalizing([$t[1]->id, $t[2]->id], $b->entries()->pluck('tournament_team_id')->all());

        // Um classificado por grupo: só a final, 1A × 1B.
        $final = $category->stages()->where('type', Stage::TYPE_KNOCKOUT)->firstOrFail()->matches()->sole();
        $this->assertSame([$a->id, $b->id], [$final->home_source_group_id, $final->away_source_group_id]);
    }

    public function test_gerar_de_novo_tira_do_site_e_resultado_trava(): void
    {
        $category = $this->category($this->tournament);
        $this->teams($category, 4);

        $this->brackets->generate($category, Stage::GENERATION_DRAW, $this->organizer);
        $this->brackets->publish($category, true);
        $this->assertNotNull($category->fresh()->bracket_published_at);

        $this->brackets->generate($category, Stage::GENERATION_DRAW, $this->organizer);
        $this->assertNull($category->fresh()->bracket_published_at);
        $this->assertSame(1, $category->stages()->count());
        $this->assertSame(3, Game::count());

        $first = $this->knockout($category)->matches()->where('round', 1)->first();
        $this->finish($first, $first->home_team_id);

        // Com resultado, gerar de novo ou desfazer só avisa; confirmado, apaga.
        foreach ([fn () => $this->brackets->generate($category, Stage::GENERATION_DRAW, $this->organizer), fn () => $this->brackets->clear($category)] as $action) {
            try {
                $action();
                $this->fail('Com resultado, o chaveamento pede confirmação.');
            } catch (ConfirmationRequired $e) {
                $this->assertStringContainsString('1 jogo(s) com resultado', $e->getMessage());
            }
        }
        $this->assertSame(Game::STATUS_FINISHED, $first->fresh()->status);

        $this->brackets->generate($category, Stage::GENERATION_DRAW, $this->organizer, force: true);
        $this->assertSame(0, Game::whereIn('status', [Game::STATUS_FINISHED, Game::STATUS_WALKOVER])->count());

        $this->finish($second = $this->knockout($category)->matches()->where('round', 1)->first(), $second->home_team_id);
        $this->brackets->clear($category, true);
        $this->assertSame(0, $category->stages()->count());
    }

    public function test_formato_com_chaveamento_gerado_avisa_e_desfaz(): void
    {
        $category = $this->category($this->tournament);
        $this->teams($category, 2);
        $this->brackets->generate($category, Stage::GENERATION_DRAW, $this->organizer);

        $setup = $this->app->make(TournamentSetupService::class);
        $base = ['price' => 100, 'team_formation' => TournamentCategory::FORMATION_ORGANIZER, 'format' => TournamentCategory::FORMAT_SINGLE_ELIMINATION, 'sets_to_win' => 2];

        // Preço muda.
        $setup->updateCategory($category, $base);
        $this->assertEquals(100, $category->fresh()->price);

        $groups = ['format' => TournamentCategory::FORMAT_GROUPS_KNOCKOUT, 'groups_count' => 1, 'qualifiers_per_group' => 2] + $base;
        try {
            $setup->updateCategory($category->fresh(), $groups);
            $this->fail('Formato com chaveamento gerado pede confirmação.');
        } catch (ConfirmationRequired $e) {
            $this->assertStringContainsString('desfaz o chaveamento', $e->getMessage());
        }
        $this->assertSame(TournamentCategory::FORMAT_SINGLE_ELIMINATION, $category->fresh()->format);

        // Confirmado: o chaveamento sai e o formato muda.
        $setup->updateCategory($category->fresh(), $groups, true);
        $this->assertSame(TournamentCategory::FORMAT_GROUPS_KNOCKOUT, $category->fresh()->format);
        $this->assertSame(0, $category->stages()->count());
    }

    public function test_tela_gera_divulga_e_marca_data(): void
    {
        $category = $this->category($this->tournament);
        $this->teams($category, 3);
        $semAcesso = $this->makeUser('Sem Acesso');

        $this->actingAs($semAcesso)->get(route('tournaments.bracket.show', [$this->tournament, $category]))->assertForbidden();

        $this->actingAs($this->organizer)->get(route('tournaments.bracket.show', [$this->tournament, $category]))
            ->assertOk()
            ->assertSee('Gerar por sorteio');

        $this->actingAs($this->organizer)->post(route('tournaments.bracket.generate', [$this->tournament, $category]), ['generation' => Stage::GENERATION_DRAW])
            ->assertRedirect(route('tournaments.bracket.show', [$this->tournament, $category]));

        $this->actingAs($this->organizer)->get(route('tournaments.bracket.show', [$this->tournament, $category]))
            ->assertOk()
            ->assertSee('Semifinal')
            ->assertSee('Final')
            ->assertSee('Divulgar no site');

        $this->actingAs($this->organizer)->put(route('tournaments.bracket.publish', [$this->tournament, $category]), ['publish' => 1])->assertRedirect();
        $this->assertNotNull($category->fresh()->bracket_published_at);

        $match = Game::where('status', Game::STATUS_PENDING)->where('round', 1)->firstOrFail();
        $this->actingAs($this->organizer)->put(route('tournaments.bracket.schedule', [$this->tournament, $category, $match]), [
            'scheduled_at' => '2026-11-28T09:00',
            'location_note' => 'Quadra 2',
        ])->assertRedirect();
        $this->assertSame('Quadra 2', $match->fresh()->location_note);
        $this->assertSame('2026-11-28 09:00', $match->fresh()->scheduled_at->format('Y-m-d H:i'));

        // A tela do torneio leva ao chaveamento.
        $this->actingAs($this->organizer)->get(route('tournaments.show', $this->tournament))
            ->assertOk()
            ->assertSee(route('tournaments.bracket.show', [$this->tournament, $category]));
    }

    public function test_tela_com_grupos_mostra_classificacao(): void
    {
        $category = $this->category($this->tournament, [
            'format' => TournamentCategory::FORMAT_GROUPS_KNOCKOUT,
            'groups_count' => 2,
            'qualifiers_per_group' => 2,
        ]);
        $this->teams($category, 6);
        $this->brackets->generate($category, Stage::GENERATION_DRAW, $this->organizer);

        $this->actingAs($this->organizer)->get(route('tournaments.bracket.show', [$this->tournament, $category]))
            ->assertOk()
            ->assertSee('Fase de grupos')
            ->assertSee('Grupo B')
            ->assertSee('1º do Grupo A')
            ->assertSee('Mata-mata');
    }

    public function test_api_so_mostra_depois_de_divulgar(): void
    {
        $category = $this->category($this->tournament, [
            'format' => TournamentCategory::FORMAT_GROUPS_KNOCKOUT,
            'groups_count' => 2,
            'qualifiers_per_group' => 2,
        ]);
        $this->teams($category, 4);
        $headers = ['Authorization' => 'Bearer token-de-teste', 'Accept' => 'application/json'];
        $url = "/api/tournaments/{$this->tournament->id}/categories/{$category->id}/bracket";

        $this->withHeaders($headers)->getJson($url)
            ->assertNotFound()
            ->assertJsonPath('reason', TournamentException::BRACKET_NOT_PUBLISHED);

        $this->brackets->generate($category, Stage::GENERATION_DRAW, $this->organizer);
        $this->brackets->publish($category, true);

        $this->withHeaders($headers)->getJson($url)
            ->assertOk()
            ->assertJsonPath('format', TournamentCategory::FORMAT_GROUPS_KNOCKOUT)
            ->assertJsonPath('stages.0.type', Stage::TYPE_GROUP)
            ->assertJsonCount(2, 'stages.0.groups')
            ->assertJsonCount(2, 'stages.0.groups.0.standings')
            ->assertJsonPath('stages.0.groups.0.matches.0.status', Game::STATUS_PENDING)
            ->assertJsonPath('stages.1.type', Stage::TYPE_KNOCKOUT)
            ->assertJsonPath('stages.1.matches.0.round_label', 'Semifinal')
            ->assertJsonPath('stages.1.matches.0.home.source.label', '1º do Grupo A')
            ->assertJsonPath('stages.1.matches.0.home.team', null);

        $this->withHeaders($headers)->getJson("/api/tournaments/{$this->tournament->id}")
            ->assertOk()
            ->assertJsonPath('categories.0.bracket_published', true);

        // Categoria de outro torneio.
        $other = $this->category($this->tournament(['title' => 'Outro']));
        $this->withHeaders($headers)->getJson("/api/tournaments/{$this->tournament->id}/categories/{$other->id}/bracket")
            ->assertNotFound()
            ->assertJsonPath('reason', TournamentException::CATEGORY_NOT_FOUND);
    }
}
