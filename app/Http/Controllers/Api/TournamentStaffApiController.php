<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\TournamentException;
use App\Http\Controllers\Controller;
use App\Models\Tournament\Tournament;
use App\Models\Tournament\TournamentMatch;
use App\Services\Tournament\TournamentBracketPresenter;
use App\Services\Tournament\TournamentResultCodeService;
use App\Services\Tournament\TournamentResultService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Lançamento de resultados pelo site (`/api/tournaments/{id}/staff/*`), pela
 * equipe do clube, com o **código de lançamento** do torneio (gerado no
 * Lara por quem tem `torneios.resultados`).
 *
 * Pede o `api_token` e o código no header `Tournament-Code` (no `check`, no
 * corpo). Código errado, vencido ou retirado: 401 `invalid_code`. O jogo
 * lançado no Lara está travado (`match_locked`): o Lara prevalece.
 *
 * Quem lançou: o site manda `operator_name` (o nome de quem está lançando),
 * que vai para o histórico do jogo.
 */
class TournamentStaffApiController extends Controller
{
    public function __construct(
        private TournamentResultCodeService $codes,
        private TournamentResultService $results,
        private TournamentBracketPresenter $presenter,
    ) {
    }

    /** Confere o código antes de abrir a tela de lançamento. */
    public function check(Request $request, int $id): JsonResponse
    {
        $tournament = Tournament::find($id);

        if (! $tournament || ! $this->codes->check($tournament, $request->input('code'))) {
            return $this->invalidCode();
        }

        return response()->json([
            'tournament' => ['id' => $tournament->id, 'title' => $tournament->title, 'status' => $tournament->status],
            'valid_until' => $tournament->results_code_expires_at->toIso8601String(),
        ]);
    }

    /** Todos os jogos do torneio, por categoria com chaveamento gerado (divulgado ou não). */
    public function matches(Request $request, int $id): JsonResponse
    {
        $tournament = $this->authorized($request, $id);

        if (! $tournament) {
            return $this->invalidCode();
        }

        $categories = $tournament->categories()->whereHas('stages')->get();

        return response()->json([
            'tournament' => ['id' => $tournament->id, 'title' => $tournament->title, 'status' => $tournament->status],
            'categories' => $categories->map(fn ($category) => $this->presenter->category($category, staff: true))->values(),
        ]);
    }

    public function record(Request $request, int $id, int $matchId): JsonResponse
    {
        $tournament = $this->authorized($request, $id);

        if (! $tournament) {
            return $this->invalidCode();
        }

        $match = TournamentMatch::with('stage.category')->find($matchId);

        if (! $match || $match->stage?->category?->tournament_id !== $tournament->id) {
            return response()->json(['message' => 'Jogo não encontrado.', 'reason' => TournamentException::MATCH_NOT_FOUND], 404);
        }

        $data = Validator::make($request->all(), [
            'operator_name' => 'required|string|max:120',
            'walkover_winner_team_id' => 'nullable|integer',
            'sets' => 'required_without:walkover_winner_team_id|array|max:9',
            'sets.*.home_games' => 'required|integer|min:0|max:99',
            'sets.*.away_games' => 'required|integer|min:0|max:99',
            'sets.*.home_tiebreak' => 'nullable|integer|min:0|max:99',
            'sets.*.away_tiebreak' => 'nullable|integer|min:0|max:99',
        ])->validate();

        try {
            $match = $this->results->record(
                $match,
                $data['sets'] ?? [],
                TournamentResultService::ORIGIN_SITE,
                operator: trim($data['operator_name']),
                walkoverWinnerId: isset($data['walkover_winner_team_id']) ? (int) $data['walkover_winner_team_id'] : null,
            );
        } catch (TournamentException $e) {
            return response()->json($e->toArray(), $e->status);
        }

        $match->load(['homeTeam.registrations', 'awayTeam.registrations', 'sets']);

        return response()->json($this->presenter->match($match, [], null, staff: true));
    }

    private function authorized(Request $request, int $id): ?Tournament
    {
        $tournament = Tournament::find($id);

        return $tournament && $this->codes->check($tournament, $request->header('Tournament-Code')) ? $tournament : null;
    }

    private function invalidCode(): JsonResponse
    {
        return response()->json(['message' => 'Código de lançamento inválido ou vencido.', 'reason' => TournamentException::INVALID_CODE], 401);
    }
}
