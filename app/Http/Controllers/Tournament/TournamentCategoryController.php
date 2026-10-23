<?php

namespace App\Http\Controllers\Tournament;

use App\Http\Controllers\Controller;
use App\Models\Tournament\Tournament;
use App\Models\Tournament\TournamentCategory;
use App\Models\Tournament\TournamentCategoryTemplate;
use App\Services\Tournament\TournamentSetupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Categorias ligadas a um torneio (permissão `torneios.gerenciar`): escolhe
 * do catálogo do grupo e define preço, vagas, montagem dos times, formato e
 * sets. Regras em TournamentSetupService.
 */
class TournamentCategoryController extends Controller
{
    public function __construct(private TournamentSetupService $setup)
    {
    }

    public function store(Request $request, Tournament $tournament): RedirectResponse
    {
        $data = $request->validate(['category_template_id' => 'required|integer'] + TournamentSetupService::linkRules(), [], $this->attributes());

        $template = TournamentCategoryTemplate::findOrFail($data['category_template_id']);
        $category = $this->setup->attachCategory($tournament, $template, $data);

        return redirect()->route('tournaments.show', $tournament)->with('success', "Categoria {$category->name} ligada ao torneio.");
    }

    public function update(Request $request, Tournament $tournament, TournamentCategory $category): RedirectResponse
    {
        abort_unless($category->tournament_id === $tournament->id, 404);

        $this->setup->updateCategory($category, $request->validate(TournamentSetupService::linkRules(), [], $this->attributes()));

        return redirect()->route('tournaments.show', $tournament)->with('success', "Categoria {$category->name} atualizada.");
    }

    public function destroy(Tournament $tournament, TournamentCategory $category): RedirectResponse
    {
        abort_unless($category->tournament_id === $tournament->id, 404);

        $this->setup->detachCategory($category);

        return redirect()->route('tournaments.show', $tournament)->with('success', "Categoria {$category->name} saiu do torneio.");
    }

    private function attributes(): array
    {
        return [
            'category_template_id' => 'categoria',
            'price' => 'preço',
            'max_registrations' => 'vagas',
            'team_formation' => 'montagem dos times',
            'format' => 'formato',
            'groups_count' => 'número de grupos',
            'qualifiers_per_group' => 'classificados por grupo',
            'sets_to_win' => 'sets para vencer',
        ];
    }
}
