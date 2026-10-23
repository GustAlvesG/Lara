<?php

namespace App\Http\Controllers\Tournament;

use App\Http\Controllers\Controller;
use App\Models\PlaceGroup;
use App\Models\Tournament\Tournament;
use App\Models\Tournament\TournamentCategory;
use App\Models\Tournament\TournamentCategoryTemplate;
use App\Models\Tournament\TournamentMatch;
use App\Models\Tournament\TournamentRegistration;
use App\Models\Tournament\TournamentTeam;
use App\Services\Tournament\TournamentSetupService;
use App\Support\ImageSource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Torneios no painel (permissão `torneios.gerenciar`): lista, cadastro,
 * situação e as categorias ligadas (TournamentCategoryController). As
 * inscrições têm tela própria (TournamentRegistrationController).
 */
class TournamentController extends Controller
{
    public function __construct(private TournamentSetupService $setup)
    {
    }

    public function index(Request $request): View
    {
        $tournaments = Tournament::with('placeGroup')
            ->withCount('categories')
            ->when($request->filled('q'), fn ($q) => $q->where('title', 'like', '%' . $request->input('q') . '%'))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->orderByDesc('starts_on')
            ->paginate(20)
            ->withQueryString();

        return view('tournaments.index', ['tournaments' => $tournaments]);
    }

    public function create(): View
    {
        return view('tournaments.form', ['tournament' => null, 'groups' => $this->groups()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;
        $data['status'] = Tournament::STATUS_DRAFT;

        $tournament = Tournament::create($data);

        return redirect()->route('tournaments.show', $tournament)->with('success', 'Torneio criado em rascunho. Ligue as categorias e publique.');
    }

    public function show(Tournament $tournament): View
    {
        // Andamento por categoria: times completos e jogos encerrados.
        $tournament->load(['placeGroup', 'resultsCodeCreator', 'categories' => fn ($q) => $q->withCount([
            'stages',
            'teams as active_teams_count' => fn ($t) => $t->where('status', '!=', TournamentTeam::STATUS_CANCELLED),
            'teams as complete_teams_count' => fn ($t) => $t->where('status', TournamentTeam::STATUS_COMPLETE),
            'matches as playable_matches_count' => fn ($m) => $m->where('status', '!=', TournamentMatch::STATUS_BYE),
            'matches as played_matches_count' => fn ($m) => $m->whereIn('status', [TournamentMatch::STATUS_FINISHED, TournamentMatch::STATUS_WALKOVER]),
        ])]);

        $counts = TournamentRegistration::whereIn('tournament_category_id', $tournament->categories->pluck('id'))
            ->selectRaw('tournament_category_id, status, count(*) as total')
            ->groupBy('tournament_category_id', 'status')
            ->get()
            ->groupBy('tournament_category_id');

        $linked = $tournament->categories->pluck('category_template_id')->filter();

        return view('tournaments.show', [
            'tournament' => $tournament,
            'counts' => $counts,
            'templates' => TournamentCategoryTemplate::active()
                ->where('place_group_id', $tournament->place_group_id)
                ->whereNotIn('id', $linked)
                ->orderBy('name')
                ->get(),
            'transitions' => TournamentSetupService::TRANSITIONS[$tournament->status] ?? [],
        ]);
    }

    public function edit(Tournament $tournament): View
    {
        return view('tournaments.form', ['tournament' => $tournament, 'groups' => $this->groups()]);
    }

    public function update(Request $request, Tournament $tournament): RedirectResponse
    {
        $tournament->update($this->validated($request, $tournament));

        return redirect()->route('tournaments.show', $tournament)->with('success', 'Torneio atualizado.');
    }

    public function status(Request $request, Tournament $tournament): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(Tournament::STATUS_LABELS))]]);

        $this->setup->changeStatus($tournament, $data['status']);

        return redirect()->route('tournaments.show', $tournament)
            ->with('success', 'Situação: ' . Tournament::STATUS_LABELS[$data['status']] . '.');
    }

    private function groups()
    {
        return PlaceGroup::sport()->orderBy('name')->get(['id', 'name']);
    }

    private function validated(Request $request, ?Tournament $tournament = null): array
    {
        $data = $request->validate([
            'place_group_id' => [
                'required',
                Rule::in($this->groups()->pluck('id')->all()),
            ],
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'rules' => 'nullable|string|max:60000',
            'registration_starts_at' => 'required|date',
            'registration_ends_at' => 'required|date|after:registration_starts_at',
            'starts_on' => 'required|date',
            'ends_on' => 'required|date|after_or_equal:starts_on',
            'image' => 'nullable|image|max:4096',
        ], [
            'registration_ends_at.after' => 'O fim das inscrições precisa ser depois do início.',
            'ends_on.after_or_equal' => 'O último dia não pode ser antes do primeiro.',
        ], [
            'place_group_id' => 'grupo de espaços',
            'title' => 'nome',
            'registration_starts_at' => 'início das inscrições',
            'registration_ends_at' => 'fim das inscrições',
            'starts_on' => 'primeiro dia',
            'ends_on' => 'último dia',
        ]);

        // O catálogo de categorias é do grupo: com categoria ligada, o grupo fica.
        if ($tournament && (int) $data['place_group_id'] !== $tournament->place_group_id && $tournament->categories()->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'place_group_id' => 'O torneio já tem categorias deste grupo: tire-as antes de trocar o grupo.',
            ]);
        }

        $data['image'] = ImageSource::resolve($request, 'image', $tournament?->image);

        return $data;
    }
}
