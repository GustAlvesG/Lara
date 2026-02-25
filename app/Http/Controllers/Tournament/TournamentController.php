<?php

namespace App\Http\Controllers\Tournament;

use App\Services\TournamentService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class TournamentController extends Controller
{
    protected $tournamentService;

    public function __construct(TournamentService $tournamentService)
    {
        $this->tournamentService = $tournamentService;
    }

    // =========================================================================
    // 🏆 CRUD DE TORNEIOS (WEB)
    // =========================================================================

    public function index()
    {
        $tournaments = $this->tournamentService->getAllTournaments();
        // Retorna para resources/views/tournaments/index.blade.php
        return view('tournaments.tournaments.index', compact('tournaments'));
    }

    public function create()
    {
        // Retorna o formulário vazio de criação
        return view('tournaments.tournaments.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'start_date_subscription' => 'required|date',
            'end_date_subscription' => 'required|date',
            'group_id' => 'required|exists:place_groups,id',
        ]);

        $this->tournamentService->createTournament($data);
        
        // Redireciona de volta para a listagem com mensagem de sucesso
        return redirect()->route('tournaments.index')->with('success', 'Torneio criado com sucesso!');
    }

    public function show($id)
    {
        $tournament = $this->tournamentService->getTournamentById($id);
        return view('tournaments.show', compact('tournament'));
    }

    public function edit($id)
    {
        // Busca o torneio para preencher os dados do formulário de edição
        $tournament = $this->tournamentService->getTournamentById($id);
        return view('tournaments.edit', compact('tournament'));
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'title' => 'string|max:255',
            'start_date' => 'date',
            // ... outras validações
        ]);

        $this->tournamentService->updateTournament($id, $data);
        return redirect()->route('tournaments.index')->with('success', 'Torneio atualizado com sucesso!');
    }

    public function destroy($id)
    {
        $this->tournamentService->deleteTournament($id);
        return redirect()->route('tournaments.index')->with('success', 'Torneio removido com sucesso!');
    }

    // =========================================================================
    // 🏷️ CRUD DE CATEGORIAS (WEB)
    // =========================================================================

    public function indexCategories()
    {
        $categories = $this->tournamentService->getAllCategories();
        // Retorna para resources/views/categories/index.blade.php
        return view('categories.index', compact('categories'));
    }

    public function createCategory()
    {
        return view('categories.create');
    }

    public function storeCategory(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'member_by_team' => 'integer|min:1'
        ]);

        $this->tournamentService->createCategory($data);
        return redirect()->route('categories.index')->with('success', 'Categoria criada com sucesso!');
    }

    public function editCategory($id)
    {
        // Neste caso, você precisa buscar a categoria no banco. 
        // Você pode criar uma função getCategoryById($id) no seu TournamentService.
        $category = \App\Models\Category::findOrFail($id); 
        return view('categories.edit', compact('category'));
    }

    public function updateCategory(Request $request, $id)
    {
        $data = $request->validate(['name' => 'string']);
        $this->tournamentService->updateCategory($id, $data);
        return redirect()->route('categories.index')->with('success', 'Categoria atualizada com sucesso!');
    }

    public function destroyCategory($id)
    {
        $this->tournamentService->deleteCategory($id);
        return redirect()->route('categories.index')->with('success', 'Categoria removida com sucesso!');
    }
}