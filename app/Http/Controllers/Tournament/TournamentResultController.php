<?php

namespace App\Http\Controllers\Tournament;

use App\Exceptions\TournamentException;
use App\Http\Controllers\Controller;
use App\Models\Tournament\Tournament;
use App\Models\Tournament\TournamentCategory;
use App\Models\Tournament\TournamentGroup;
use App\Models\Tournament\TournamentMatch;
use App\Services\Tournament\TournamentResultCodeService;
use App\Services\Tournament\TournamentResultService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Resultados no painel (permissão `torneios.resultados`): lançar e corrigir
 * placar ou W.O., limpar, fechar e reabrir grupo, e o código de lançamento
 * pelo site. Lançado aqui, o jogo trava para o site (o Lara prevalece).
 * Regra que só avisa volta com "Confirmar mesmo assim" (`force`).
 * Regras em TournamentResultService e TournamentResultCodeService.
 */
class TournamentResultController extends Controller
{
    public function __construct(
        private TournamentResultService $results,
        private TournamentResultCodeService $codes,
    ) {
    }

    public function record(Request $request, Tournament $tournament, TournamentCategory $category, TournamentMatch $match): RedirectResponse
    {
        $this->assertMatch($tournament, $category, $match);

        $data = $request->validate([
            'walkover' => 'nullable|in:home,away',
            'sets' => 'nullable|array|max:9',
            'sets.*.home_games' => 'nullable|integer|min:0|max:99',
            'sets.*.away_games' => 'nullable|integer|min:0|max:99',
            'sets.*.home_tiebreak' => 'nullable|integer|min:0|max:99',
            'sets.*.away_tiebreak' => 'nullable|integer|min:0|max:99',
        ]);

        $walkover = match ($data['walkover'] ?? null) {
            'home' => $match->home_team_id,
            'away' => $match->away_team_id,
            default => null,
        };

        $this->panel(fn () => $this->results->record($match, $data['sets'] ?? [], TournamentResultService::ORIGIN_LARA, $request->user(), walkoverWinnerId: $walkover, force: $request->boolean('force')));

        return $this->back($tournament, $category, 'Resultado gravado.');
    }

    public function clear(Request $request, Tournament $tournament, TournamentCategory $category, TournamentMatch $match): RedirectResponse
    {
        $this->assertMatch($tournament, $category, $match);

        $this->panel(fn () => $this->results->clear($match, $request->user(), $request->boolean('force')));

        return $this->back($tournament, $category, 'Resultado limpo: o jogo voltou para "A jogar" e o site pode lançar de novo.');
    }

    public function closeGroup(Request $request, Tournament $tournament, TournamentCategory $category, TournamentGroup $group): RedirectResponse
    {
        $this->assertGroup($tournament, $category, $group);

        $data = $request->validate(['positions' => 'required|array', 'positions.*' => 'required|integer|min:1']);
        $this->panel(fn () => $this->results->closeGroup($group, $data['positions'], $request->user(), $request->boolean('force')));

        return $this->back($tournament, $category, "{$group->name} fechado: os classificados entraram no mata-mata.");
    }

    public function reopenGroup(Request $request, Tournament $tournament, TournamentCategory $category, TournamentGroup $group): RedirectResponse
    {
        $this->assertGroup($tournament, $category, $group);

        $this->panel(fn () => $this->results->reopenGroup($group, $request->user(), $request->boolean('force')));

        return $this->back($tournament, $category, "{$group->name} reaberto.");
    }

    public function generateCode(Request $request, Tournament $tournament): RedirectResponse
    {
        $code = $this->codes->generate($tournament, $request->user());

        return redirect()->route('tournaments.show', $tournament)
            ->with('success', 'Código de lançamento gerado. Anote agora: ele não aparece de novo.')
            ->with('results_code', $code);
    }

    public function revokeCode(Tournament $tournament): RedirectResponse
    {
        $this->codes->revoke($tournament);

        return redirect()->route('tournaments.show', $tournament)->with('success', 'Código de lançamento retirado: o site não lança mais resultado deste torneio.');
    }

    /** Recusa de regra vira erro de formulário. */
    private function panel(callable $action): void
    {
        try {
            $action();
        } catch (TournamentException $e) {
            throw ValidationException::withMessages(['result' => $e->getMessage()]);
        }
    }

    private function back(Tournament $tournament, TournamentCategory $category, string $message): RedirectResponse
    {
        return redirect()->route('tournaments.bracket.show', [$tournament, $category])->with('success', $message);
    }

    private function assertMatch(Tournament $tournament, TournamentCategory $category, TournamentMatch $match): void
    {
        abort_unless($category->tournament_id === $tournament->id && $match->stage?->tournament_category_id === $category->id, 404);
    }

    private function assertGroup(Tournament $tournament, TournamentCategory $category, TournamentGroup $group): void
    {
        abort_unless($category->tournament_id === $tournament->id && $group->stage?->tournament_category_id === $category->id, 404);
    }
}
