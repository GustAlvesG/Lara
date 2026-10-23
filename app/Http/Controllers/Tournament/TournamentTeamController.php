<?php

namespace App\Http\Controllers\Tournament;

use App\Http\Controllers\Controller;
use App\Models\Tournament\Tournament;
use App\Models\Tournament\TournamentCategory;
use App\Models\Tournament\TournamentTeam;
use App\Models\Tournament\TournamentTeamInvite;
use App\Services\Tournament\TournamentTeamService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Times de uma categoria no painel (permissão `torneios.inscricoes`): o
 * organizador monta, sorteia, troca atleta e capitão, e desfaz — em qualquer
 * categoria e a qualquer momento (depois do fim das inscrições, só ele mexe).
 * Regras em TournamentTeamService.
 */
class TournamentTeamController extends Controller
{
    public function __construct(private TournamentTeamService $teams)
    {
    }

    public function index(Tournament $tournament, TournamentCategory $category): View
    {
        $this->assertBelongs($tournament, $category);

        return view('tournaments.teams', [
            'tournament' => $tournament,
            'category' => $category,
            'teams' => $category->teams()
                ->with(['registrations', 'captain', 'invites' => fn ($q) => $q->where('status', TournamentTeamInvite::STATUS_PENDING)])
                ->orderBy('name')
                ->get()
                // Desfeitos por último.
                ->sortBy(fn ($t) => $t->status === TournamentTeam::STATUS_CANCELLED ? 1 : 0)
                ->values(),
            'unassigned' => $this->teams->unassigned($category),
        ]);
    }

    public function store(Request $request, Tournament $tournament, TournamentCategory $category): RedirectResponse
    {
        $this->assertBelongs($tournament, $category);

        $data = $request->validate([
            'name' => 'nullable|string|max:255',
            'registration_ids' => 'required|array|min:1',
            'registration_ids.*' => 'integer',
        ], ['registration_ids.required' => 'Marque quem entra no time.']);

        $registrations = $category->registrations()->whereIn('id', $data['registration_ids'])->get();
        $team = $this->teams->create(
            $category,
            filled($data['name'] ?? null) ? $data['name'] : $this->teams->nameFor($registrations),
            $data['registration_ids'],
            $request->user(),
        );

        return $this->back($tournament, $category, "Time {$team->name} criado.");
    }

    public function update(Request $request, Tournament $tournament, TournamentCategory $category, TournamentTeam $team): RedirectResponse
    {
        $this->assertTeam($tournament, $category, $team);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'seed' => 'nullable|integer|min:1|max:999',
            'captain_registration_id' => 'nullable|integer',
        ], [], ['name' => 'nome', 'seed' => 'cabeça de chave']);

        $this->teams->update($team, $data['name'], $data['seed'] ?? null);
        $this->teams->setCaptain($team, $data['captain_registration_id'] ?? null);

        return $this->back($tournament, $category, "Time {$team->name} atualizado.");
    }

    public function addMember(Request $request, Tournament $tournament, TournamentCategory $category, TournamentTeam $team): RedirectResponse
    {
        $this->assertTeam($tournament, $category, $team);

        $data = $request->validate(['registration_id' => 'required|integer'], ['registration_id.required' => 'Escolha quem entra no time.']);
        $this->teams->addMember($team, (int) $data['registration_id'], $request->user());

        return $this->back($tournament, $category, "Atleta incluído no time {$team->name}.");
    }

    public function removeMember(Tournament $tournament, TournamentCategory $category, TournamentTeam $team, int $registration): RedirectResponse
    {
        $this->assertTeam($tournament, $category, $team);

        $this->teams->removeMember($team, $registration);

        return $this->back($tournament, $category, "Atleta retirado do time {$team->name}. A inscrição continua, sem time.");
    }

    public function destroy(Tournament $tournament, TournamentCategory $category, TournamentTeam $team): RedirectResponse
    {
        $this->assertTeam($tournament, $category, $team);

        $this->teams->cancel($team);

        return $this->back($tournament, $category, "Time {$team->name} desfeito. As inscrições continuam, sem time.");
    }

    public function draw(Request $request, Tournament $tournament, TournamentCategory $category): RedirectResponse
    {
        $this->assertBelongs($tournament, $category);

        $result = $this->teams->draw($category, $request->user());
        $message = $result['teams']->count() . ' time(s) sorteado(s).'
            . ($result['leftover'] > 0 ? " {$result['leftover']} pessoa(s) paga(s) ficaram sem time: monte à mão ou espere mais inscrições." : '');

        return $this->back($tournament, $category, $message);
    }

    private function back(Tournament $tournament, TournamentCategory $category, string $message): RedirectResponse
    {
        return redirect()->route('tournaments.teams.index', [$tournament, $category])->with('success', $message);
    }

    private function assertBelongs(Tournament $tournament, TournamentCategory $category): void
    {
        abort_unless($category->tournament_id === $tournament->id, 404);
    }

    private function assertTeam(Tournament $tournament, TournamentCategory $category, TournamentTeam $team): void
    {
        $this->assertBelongs($tournament, $category);
        abort_unless($team->tournament_category_id === $category->id, 404);
    }
}
