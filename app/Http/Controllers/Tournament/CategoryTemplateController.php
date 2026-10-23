<?php

namespace App\Http\Controllers\Tournament;

use App\Http\Controllers\Controller;
use App\Models\PlaceGroup;
use App\Models\Tournament\TournamentCategoryTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Catálogo de categorias de torneio de cada grupo de espaços (permissão
 * `torneios.gerenciar`). Só grupos esportivos: espaço social não tem torneio.
 *
 * Mudar ou remover uma categoria não mexe em torneio já criado: o torneio
 * guarda a cópia (TournamentCategory).
 */
class CategoryTemplateController extends Controller
{
    public function index(Request $request): View
    {
        $groups = PlaceGroup::sport()->orderBy('name')->get(['id', 'name']);
        $group = $this->selectedGroup($groups, $request->integer('group'));

        return view('tournaments.categories', [
            'groups' => $groups,
            'group' => $group,
            'templates' => $group
                ? TournamentCategoryTemplate::where('place_group_id', $group->id)->orderByDesc('active')->orderBy('name')->get()
                : collect(),
            'editing' => $group && $request->filled('edit')
                ? TournamentCategoryTemplate::where('place_group_id', $group->id)->find($request->integer('edit'))
                : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $template = TournamentCategoryTemplate::create($data);

        return $this->back($template, 'Categoria cadastrada.');
    }

    public function update(Request $request, TournamentCategoryTemplate $template): RedirectResponse
    {
        $template->update($this->validated($request, $template));

        return $this->back($template, 'Categoria atualizada.');
    }

    public function destroy(TournamentCategoryTemplate $template): RedirectResponse
    {
        $template->delete();

        return $this->back($template, 'Categoria removida do catálogo.');
    }

    private function selectedGroup(Collection $groups, int $id): ?PlaceGroup
    {
        return $groups->firstWhere('id', $id) ?? $groups->first();
    }

    private function back(TournamentCategoryTemplate $template, string $message): RedirectResponse
    {
        return redirect()
            ->route('tournaments.categories.index', ['group' => $template->place_group_id])
            ->with('success', $message);
    }

    private function validated(Request $request, ?TournamentCategoryTemplate $template = null): array
    {
        $groupId = $template?->place_group_id ?? $request->integer('place_group_id');

        $data = $request->validate([
            'place_group_id' => [$template ? 'nullable' : 'required', Rule::exists('place_groups', 'id')->whereNull('deleted_at')],
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('tournament_category_templates', 'name')
                    ->where('place_group_id', $groupId)
                    ->whereNull('deleted_at')
                    ->ignore($template?->id),
            ],
            'team_size' => 'required|integer|min:1|max:50',
            'gender' => ['required', Rule::in(array_keys(TournamentCategoryTemplate::GENDERS))],
            'min_age' => 'nullable|integer|min:0|max:120',
            'max_age' => 'nullable|integer|min:0|max:120' . ($request->filled('min_age') ? '|gte:min_age' : ''),
        ], [
            'name.unique' => 'Este grupo já tem uma categoria com esse nome.',
            'max_age.gte' => 'A idade máxima não pode ser menor que a mínima.',
        ], [
            'name' => 'nome',
            'team_size' => 'pessoas por equipe',
            'gender' => 'sexo',
            'min_age' => 'idade mínima',
            'max_age' => 'idade máxima',
        ]);

        // O grupo não muda na edição: a categoria pertence ao book dele.
        $data['place_group_id'] = $groupId;
        $data['active'] = $request->boolean('active');

        return $data;
    }
}
