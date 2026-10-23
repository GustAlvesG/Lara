<?php

namespace Tests\Feature\Tournament;

use App\Exceptions\TournamentException;
use App\Models\Tournament\Tournament;
use App\Models\Tournament\TournamentCategory;
use App\Models\Tournament\TournamentGroup;
use App\Models\Tournament\TournamentMatch as Game;
use App\Models\Tournament\TournamentMatchResultLog;
use App\Models\Tournament\TournamentStage as Stage;
use App\Models\Tournament\TournamentTeam as Team;
use App\Models\User;
use App\Services\Tournament\TournamentBracketService;
use App\Services\Tournament\TournamentResultCodeService;
use App\Services\Tournament\TournamentResultService as Results;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsTournaments;
use Tests\Concerns\CreatesTournamentSchema;
use Tests\TestCase;

/**
 * Torneios, F6: resultados no Lara e no site. O vencedor avança, o Lara
 * trava o jogo para o site, correção não passa por cima de jogo seguinte já
 * jogado, W.O., limpar, fechar e reabrir grupo, e o código de lançamento.
 */
class TournamentResultTest extends TestCase
{
    use CreatesTournamentSchema;
    use BuildsTournaments;

    private TournamentBracketService $brackets;
    private Results $results;
    private Tournament $tournament;
    private User $organizer;
    private array $headers = ['Authorization' => 'Bearer token-de-teste', 'Accept' => 'application/json'];

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['services.api.token' => 'token-de-teste']);

        $this->createTournamentSchema();
        $this->fakeIntegrations();
        $this->brackets = $this->app->make(TournamentBracketService::class);
        $this->results = $this->app->make(Results::class);
        $this->tournament = $this->tournament(['status' => Tournament::STATUS_IN_PROGRESS]);
        $this->organizer = $this->makeUser('Pessoa do Esporte');
        $this->joinSector($this->organizer, 'TI');
    }

    private function teams(TournamentCategory $category, int $count): array
    {
        $teams = [];
        for ($i = 1; $i <= $count; $i++) {
            $teams[$i] = Team::create(['tournament_category_id' => $category->id, 'name' => "Time {$i}", 'origin' => Team::ORIGIN_ORGANIZER, 'status' => Team::STATUS_COMPLETE, 'seed' => $i]);
        }

        return $teams;
    }

    /** Eliminatória de 4 (1 × 4, 2 × 3) com 3º lugar. */
    private function elimination(): array
    {
        $category = $this->category($this->tournament, ['third_place_match' => true, 'sets_to_win' => 2]);
        $teams = $this->teams($category, 4);
        $this->brackets->generate($category, Stage::GENERATION_DRAW, $this->organizer);
        $stage = $category->stages()->first();

        return [
            $category,
            $teams,
            $stage->matches()->where('round', 1)->orderBy('position')->get()->all(),
            $stage->matches()->where('round', 2)->where('third_place', false)->first(),
            $stage->matches()->where('third_place', true)->first(),
        ];
    }

    private function win(int $home, int $away): array
    {
        return array_fill(0, 2, ['home_games' => $home, 'away_games' => $away]);
    }

    public function test_resultado_avanca_vencedor_e_perdedor_vai_ao_terceiro_lugar(): void
    {
        [, $t, [$semi1, $semi2], $final, $third] = $this->elimination();

        $match = $this->results->record($semi1, [['home_games' => 6, 'away_games' => 4], ['home_games' => 3, 'away_games' => 6], ['home_games' => 7, 'away_games' => 6, 'home_tiebreak' => 7, 'away_tiebreak' => 4]], Results::ORIGIN_LARA, $this->organizer);

        $this->assertSame(Game::STATUS_FINISHED, $match->status);
        $this->assertSame([2, 1], [$match->home_sets, $match->away_sets]);
        $this->assertSame($t[1]->id, $match->winner_team_id);
        $this->assertCount(3, $match->sets);
        $this->assertTrue($match->locked_by_lara);
        $this->assertSame($t[1]->id, $final->fresh()->home_team_id);
        $this->assertSame($t[4]->id, $third->fresh()->home_team_id);

        // W.O. na outra semifinal.
        $this->results->record($semi2, [], Results::ORIGIN_LARA, $this->organizer, walkoverWinnerId: $t[3]->id);
        $semi2->refresh();
        $this->assertSame(Game::STATUS_WALKOVER, $semi2->status);
        $this->assertSame([0, 2], [$semi2->home_sets, $semi2->away_sets]);
        $this->assertSame($t[3]->id, $final->fresh()->away_team_id);
        $this->assertSame($t[2]->id, $third->fresh()->away_team_id);

        $this->assertSame(2, TournamentMatchResultLog::count());
    }

    public function test_site_nao_altera_o_que_o_lara_lancou(): void
    {
        [, , [$semi1]] = $this->elimination();

        $this->results->record($semi1, $this->win(6, 2), Results::ORIGIN_SITE, operator: 'Carlos');
        $this->assertFalse($semi1->fresh()->locked_by_lara);
        $this->assertSame(Results::ORIGIN_SITE, $semi1->fresh()->result_origin);

        // Site corrige o próprio lançamento.
        $this->results->record($semi1, $this->win(2, 6), Results::ORIGIN_SITE, operator: 'Carlos');

        // Lara corrige e trava.
        $this->results->record($semi1, $this->win(6, 3), Results::ORIGIN_LARA, $this->organizer);

        try {
            $this->results->record($semi1, $this->win(1, 6), Results::ORIGIN_SITE, operator: 'Carlos');
            $this->fail('O site não pode mexer no que o Lara lançou.');
        } catch (TournamentException $e) {
            $this->assertSame(TournamentException::MATCH_LOCKED, $e->reason);
        }

        $this->assertSame(['corrected', 'corrected', 'recorded'], TournamentMatchResultLog::orderByDesc('id')->get()->pluck('payload.action')->all());
    }

    public function test_correcao_que_muda_vencedor_espera_o_jogo_seguinte(): void
    {
        [, $t, [$semi1, $semi2], $final] = $this->elimination();
        $this->results->record($semi1, $this->win(6, 1), Results::ORIGIN_LARA, $this->organizer);
        $this->results->record($semi2, $this->win(6, 1), Results::ORIGIN_LARA, $this->organizer);
        $this->results->record($final->fresh(), $this->win(6, 1), Results::ORIGIN_LARA, $this->organizer);

        // Mesmo vencedor, outro placar: pode.
        $this->results->record($semi1, [['home_games' => 6, 'away_games' => 4], ['home_games' => 6, 'away_games' => 4]], Results::ORIGIN_LARA, $this->organizer);

        try {
            $this->results->record($semi1, $this->win(1, 6), Results::ORIGIN_LARA, $this->organizer);
            $this->fail('Final já jogada: o vencedor da semifinal não pode mudar.');
        } catch (TournamentException $e) {
            $this->assertSame(TournamentException::NEXT_MATCH_PLAYED, $e->reason);
        }

        // Limpa a final, e aí a semifinal pode mudar e mandar o outro time.
        $this->results->clear($final->fresh(), $this->organizer);
        $this->results->record($semi1, $this->win(1, 6), Results::ORIGIN_LARA, $this->organizer);
        $this->assertSame($t[4]->id, $final->fresh()->home_team_id);

        // Limpar a semifinal tira o time da final e destrava.
        $this->results->clear($semi1->fresh(), $this->organizer);
        $this->assertNull($final->fresh()->home_team_id);
        $this->assertSame(Game::STATUS_PENDING, $semi1->fresh()->status);
        $this->assertFalse($semi1->fresh()->locked_by_lara);
    }

    public function test_placar_invalido_e_jogo_sem_os_dois_times(): void
    {
        [, , [$semi1], $final] = $this->elimination();

        try {
            $this->results->record($semi1, [['home_games' => 6, 'away_games' => 4]], Results::ORIGIN_LARA, $this->organizer);
            $this->fail('Um set só num jogo de 2 sets para vencer.');
        } catch (TournamentException $e) {
            $this->assertSame(TournamentException::INVALID_SCORE, $e->reason);
        }

        $this->expectException(TournamentException::class);
        $this->results->record($final, $this->win(6, 1), Results::ORIGIN_LARA, $this->organizer);
    }

    public function test_grupo_fecha_classifica_e_reabre(): void
    {
        $category = $this->category($this->tournament, [
            'format' => TournamentCategory::FORMAT_GROUPS_KNOCKOUT,
            'groups_count' => 2,
            'qualifiers_per_group' => 2,
            'sets_to_win' => 1,
        ]);
        $this->teams($category, 4);
        $this->brackets->generate($category, Stage::GENERATION_DRAW, $this->organizer);
        [$a, $b] = TournamentGroup::orderBy('order')->get()->all();

        try {
            $this->results->closeGroup($a, [], $this->organizer);
            $this->fail('Grupo com jogo pendente não fecha.');
        } catch (TournamentException $e) {
            $this->assertStringContainsString('Faltam', $e->getMessage());
        }

        foreach ([$a, $b] as $group) {
            foreach ($group->matches as $match) {
                $this->results->record($match, [['home_games' => 6, 'away_games' => 2]], Results::ORIGIN_SITE, operator: 'Carlos');
            }
        }

        $aTeams = $a->entries()->pluck('tournament_team_id')->all();
        $bTeams = $b->entries()->pluck('tournament_team_id')->all();
        $this->results->closeGroup($a, [$aTeams[0] => 2, $aTeams[1] => 1], $this->organizer);
        $this->results->closeGroup($b, [$bTeams[0] => 1, $bTeams[1] => 2], $this->organizer);

        // 1A × 2B e 1B × 2A.
        $knockout = $category->stages()->where('type', Stage::TYPE_KNOCKOUT)->first();
        [$m1, $m2] = $knockout->matches()->where('round', 1)->orderBy('position')->get()->all();
        $this->assertSame([$aTeams[1], $bTeams[1]], [$m1->home_team_id, $m1->away_team_id]);
        $this->assertSame([$bTeams[0], $aTeams[0]], [$m2->home_team_id, $m2->away_team_id]);

        // Grupo fechado: resultado dele não muda.
        try {
            $this->results->record($a->matches()->first(), [['home_games' => 2, 'away_games' => 6]], Results::ORIGIN_LARA, $this->organizer);
            $this->fail('Grupo fechado.');
        } catch (TournamentException $e) {
            $this->assertSame(TournamentException::GROUP_CLOSED, $e->reason);
        }

        // Reabre: os classificados saem do mata-mata.
        $this->results->reopenGroup($a, $this->organizer);
        $this->assertNull($m1->fresh()->home_team_id);
        $this->assertNull($m2->fresh()->away_team_id);
        $this->assertSame($bTeams[1], $m1->fresh()->away_team_id);

        // Com o mata-mata jogado, o grupo não reabre.
        $this->results->closeGroup($a, [$aTeams[0] => 1, $aTeams[1] => 2], $this->organizer);
        $this->results->record($m1->fresh(), [['home_games' => 6, 'away_games' => 0]], Results::ORIGIN_LARA, $this->organizer);
        $this->expectException(TournamentException::class);
        $this->results->reopenGroup($b, $this->organizer);
    }

    public function test_torneio_encerrado_nao_aceita_site_e_rascunho_nao_aceita_ninguem(): void
    {
        [, , [$semi1]] = $this->elimination();

        $this->tournament->update(['status' => Tournament::STATUS_FINISHED]);
        try {
            $this->results->record($semi1, $this->win(6, 1), Results::ORIGIN_SITE, operator: 'X');
            $this->fail('Encerrado: o site não lança.');
        } catch (TournamentException $e) {
            $this->assertSame(TournamentException::RESULTS_CLOSED, $e->reason);
        }
        $this->results->record($semi1, $this->win(6, 1), Results::ORIGIN_LARA, $this->organizer);

        $this->tournament->update(['status' => Tournament::STATUS_CANCELLED]);
        $this->expectException(TournamentException::class);
        $this->results->record($semi1, $this->win(6, 2), Results::ORIGIN_LARA, $this->organizer);
    }

    public function test_codigo_de_lancamento_e_api_da_equipe(): void
    {
        [$category, $t, [$semi1, $semi2]] = $this->elimination();
        $base = "/api/tournaments/{$this->tournament->id}/staff";

        $this->withHeaders($this->headers)->postJson("{$base}/check", ['code' => 'ABCD-EFGH'])
            ->assertStatus(401)->assertJsonPath('reason', TournamentException::INVALID_CODE);

        $code = $this->app->make(TournamentResultCodeService::class)->generate($this->tournament, $this->organizer);
        $this->assertMatchesRegularExpression('/^[A-Z2-9]{4}-[A-Z2-9]{4}$/', $code);
        $this->assertNotSame($code, $this->tournament->fresh()->results_code_hash);

        // Minúsculas e sem hífen também valem.
        $this->withHeaders($this->headers)->postJson("{$base}/check", ['code' => strtolower(str_replace('-', '', $code))])
            ->assertOk()->assertJsonPath('tournament.id', $this->tournament->id);

        $staff = $this->headers + ['Tournament-Code' => $code];
        $this->withHeaders($staff)->getJson("{$base}/matches")
            ->assertOk()
            ->assertJsonPath('categories.0.category_id', $category->id)
            ->assertJsonPath('categories.0.stages.0.matches.0.editable', true)
            ->assertJsonPath('categories.0.stages.0.matches.0.locked', false);

        $this->withHeaders($staff)->putJson("{$base}/matches/{$semi1->id}/result", [
            'operator_name' => 'Carlos da quadra',
            'sets' => $this->win(6, 3),
        ])->assertOk()->assertJsonPath('status', Game::STATUS_FINISHED)->assertJsonPath('winner_team_id', $t[1]->id);
        $this->assertSame('Carlos da quadra', TournamentMatchResultLog::latest('id')->first()->payload['operator']);

        // Placar empatado.
        $this->withHeaders($staff)->putJson("{$base}/matches/{$semi2->id}/result", [
            'operator_name' => 'Carlos', 'sets' => [['home_games' => 6, 'away_games' => 6]],
        ])->assertStatus(422)->assertJsonPath('reason', TournamentException::INVALID_SCORE);

        // Lara trava; site recebe 409.
        $this->results->record($semi2, $this->win(6, 1), Results::ORIGIN_LARA, $this->organizer);
        $this->withHeaders($staff)->putJson("{$base}/matches/{$semi2->id}/result", ['operator_name' => 'Carlos', 'sets' => $this->win(1, 6)])
            ->assertStatus(409)->assertJsonPath('reason', TournamentException::MATCH_LOCKED);

        // Jogo de outro torneio.
        $other = $this->category($this->tournament(['title' => 'Outro']));
        $this->teams($other, 2);
        $this->brackets->generate($other, Stage::GENERATION_DRAW, $this->organizer);
        $foreign = $other->stages()->first()->matches()->first();
        $this->withHeaders($staff)->putJson("{$base}/matches/{$foreign->id}/result", ['operator_name' => 'Carlos', 'sets' => $this->win(6, 1)])
            ->assertNotFound()->assertJsonPath('reason', TournamentException::MATCH_NOT_FOUND);

        // Retirado, o código para de valer.
        $this->app->make(TournamentResultCodeService::class)->revoke($this->tournament);
        $this->withHeaders($staff)->getJson("{$base}/matches")->assertStatus(401);
    }

    public function test_codigo_vence_no_fim_do_torneio(): void
    {
        $codes = $this->app->make(TournamentResultCodeService::class);
        $code = $codes->generate($this->tournament, $this->organizer);

        $this->assertSame($this->tournament->ends_on->copy()->endOfDay()->toDateTimeString(), $this->tournament->fresh()->results_code_expires_at->toDateTimeString());

        $this->travelTo($this->tournament->ends_on->copy()->addDay()->setTime(8, 0));
        $this->assertFalse($codes->check($this->tournament->fresh(), $code));
    }

    public function test_painel_lanca_fecha_grupo_e_gera_codigo(): void
    {
        [$category, $t, [$semi1]] = $this->elimination();
        $semAcesso = $this->makeUser('Sem Acesso');

        $this->actingAs($semAcesso)->put(route('tournaments.results.record', [$this->tournament, $category, $semi1]), ['sets' => $this->win(6, 1)])->assertForbidden();
        $this->actingAs($semAcesso)->get(route('tournaments.bracket.show', [$this->tournament, $category]))->assertForbidden();

        $this->actingAs($this->organizer)->put(route('tournaments.results.record', [$this->tournament, $category, $semi1]), ['sets' => $this->win(6, 1)])
            ->assertRedirect(route('tournaments.bracket.show', [$this->tournament, $category]));
        $this->assertSame($t[1]->id, $semi1->fresh()->winner_team_id);

        // Placar inválido volta como erro do formulário.
        $this->actingAs($this->organizer)->from(route('tournaments.bracket.show', [$this->tournament, $category]))
            ->put(route('tournaments.results.record', [$this->tournament, $category, $semi1]), ['sets' => [['home_games' => 6, 'away_games' => 6]]])
            ->assertSessionHasErrors('result');

        $this->actingAs($this->organizer)->get(route('tournaments.bracket.show', [$this->tournament, $category]))
            ->assertOk()
            ->assertSee('Corrigir resultado')
            ->assertSee('Histórico (1)');

        $this->actingAs($this->organizer)->post(route('tournaments.results.code.store', $this->tournament))
            ->assertRedirect(route('tournaments.show', $this->tournament))
            ->assertSessionHas('results_code');
        $this->actingAs($this->organizer)->get(route('tournaments.show', $this->tournament))
            ->assertOk()
            ->assertSee('Anote agora')
            ->assertSee('4/4 time(s) completo(s)', false);

        $this->actingAs($this->organizer)->delete(route('tournaments.results.code.destroy', $this->tournament))->assertRedirect();
        $this->assertNull($this->tournament->fresh()->results_code_hash);
    }
}
