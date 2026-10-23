<?php

namespace App\Http\Controllers\Tournament;

use App\Authorization\Permissions as P;
use App\Http\Controllers\Controller;
use App\Models\Tournament\Tournament;
use App\Models\Tournament\TournamentCategory;
use App\Models\Tournament\TournamentMatch;
use App\Models\Tournament\TournamentStage;
use App\Services\Tournament\TournamentBracketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Chaveamento de uma categoria no painel (permissão `torneios.gerenciar`; a
 * tela abre também com `torneios.resultados`, para lançar placar):
 * gerar por sorteio ou à mão, gerar de novo ou desfazer (só antes do primeiro
 * resultado), divulgar no site e marcar data e local dos jogos. Regras em
 * TournamentBracketService.
 */
class TournamentBracketController extends Controller
{
    public function __construct(private TournamentBracketService $brackets)
    {
    }

    public function show(Request $request, Tournament $tournament, TournamentCategory $category): View
    {
        $this->assertBelongs($tournament, $category);
        // Abre para quem monta o chaveamento e para quem só lança resultado.
        abort_unless($request->user()->can(P::TORNEIOS_GERENCIAR) || $request->user()->can(P::TORNEIOS_RESULTADOS), 403);

        $matchRelations = ['homeTeam', 'awayTeam', 'winner', 'sets', 'resultLogs.user'];
        $stages = $category->stages()->with([
            'groups.entries.team',
            'groups.matches' => fn ($q) => $q->with($matchRelations),
            'matches' => fn ($q) => $q->whereNull('tournament_group_id')->with($matchRelations),
        ])->get();

        $teams = $this->brackets->teamsFor($category);

        return view('tournaments.bracket', [
            'tournament' => $tournament,
            'category' => $category,
            'teams' => $teams,
            'manual' => $this->manualDefaults($category, $teams, $stages),
            'stages' => $stages,
            'standings' => $stages->flatMap->groups->mapWithKeys(fn ($group) => [$group->id => $this->brackets->standings($group)]),
            'groupNames' => $stages->flatMap->groups->pluck('name', 'id'),
            'hasResults' => $this->brackets->hasResults($category),
            'outdated' => $this->brackets->outdated($category),
        ]);
    }

    public function generate(Request $request, Tournament $tournament, TournamentCategory $category): RedirectResponse
    {
        $this->assertBelongs($tournament, $category);

        $data = $request->validate([
            'generation' => 'required|in:' . TournamentStage::GENERATION_DRAW . ',' . TournamentStage::GENERATION_MANUAL,
            'slots' => 'nullable|array',
            'slots.*' => 'nullable|integer',
            'groups' => 'nullable|array',
            'groups.*' => 'nullable|integer',
        ]);

        $this->brackets->generate($category, $data['generation'], $request->user(), [
            'slots' => $data['slots'] ?? [],
            'groups' => $data['groups'] ?? [],
        ]);

        return $this->back($tournament, $category, 'Chaveamento gerado. Confira e clique em Divulgar no site quando estiver pronto.');
    }

    public function destroy(Tournament $tournament, TournamentCategory $category): RedirectResponse
    {
        $this->assertBelongs($tournament, $category);

        $this->brackets->clear($category);

        return $this->back($tournament, $category, 'Chaveamento desfeito.');
    }

    public function publish(Request $request, Tournament $tournament, TournamentCategory $category): RedirectResponse
    {
        $this->assertBelongs($tournament, $category);

        $publish = $request->boolean('publish');
        $this->brackets->publish($category, $publish);

        return $this->back($tournament, $category, $publish ? 'Chaveamento divulgado no site.' : 'Chaveamento recolhido: o site não mostra mais.');
    }

    public function schedule(Request $request, Tournament $tournament, TournamentCategory $category, TournamentMatch $match): RedirectResponse
    {
        $this->assertBelongs($tournament, $category);
        abort_unless($match->stage?->tournament_category_id === $category->id, 404);

        $data = $request->validate([
            'scheduled_at' => 'nullable|date',
            'location_note' => 'nullable|string|max:255',
        ], [], ['scheduled_at' => 'data e hora', 'location_note' => 'local']);

        $this->brackets->schedule($match, $data['scheduled_at'] ?? null, $data['location_note'] ?? null);

        return $this->back($tournament, $category, 'Jogo atualizado.');
    }

    /**
     * Valores iniciais do "montar à mão": o chaveamento atual, se houver;
     * senão a ordem dos times (cabeças de chave primeiro) nas posições-padrão
     * ou em serpentina nos grupos.
     *
     * @return array{slots: list<?int>, groups: array<int, int>}
     */
    private function manualDefaults(TournamentCategory $category, Collection $teams, Collection $stages): array
    {
        $size = TournamentBracketService::bracketSize(max(2, $teams->count()));
        $first = $stages->first();

        if ($category->format === TournamentCategory::FORMAT_GROUPS_KNOCKOUT) {
            $groups = [];
            $count = max(1, (int) $category->groups_count);

            if ($first?->type === TournamentStage::TYPE_GROUP) {
                foreach ($first->groups as $group) {
                    foreach ($group->entries as $entry) {
                        $groups[$entry->tournament_team_id] = $group->order - 1;
                    }
                }
            } else {
                foreach ($teams->values() as $i => $team) {
                    $lap = intdiv($i, $count);
                    $groups[$team->id] = $lap % 2 === 0 ? $i % $count : $count - 1 - $i % $count;
                }
            }

            return ['slots' => [], 'groups' => $groups];
        }

        $roundOne = $first?->matches->where('round', 1)->sortBy('position')->values();

        if ($roundOne && $roundOne->count() * 2 === $size) {
            $slots = $roundOne->flatMap(fn (TournamentMatch $m) => [$m->home_team_id, $m->away_team_id])->all();
        } else {
            $slots = array_map(fn (int $rank) => $teams->get($rank - 1)?->id, TournamentBracketService::seedOrder($size));
        }

        return ['slots' => $slots, 'groups' => []];
    }

    private function back(Tournament $tournament, TournamentCategory $category, string $message): RedirectResponse
    {
        return redirect()->route('tournaments.bracket.show', [$tournament, $category])->with('success', $message);
    }

    private function assertBelongs(Tournament $tournament, TournamentCategory $category): void
    {
        abort_unless($category->tournament_id === $tournament->id, 404);
    }
}
