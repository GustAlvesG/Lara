<?php

namespace App\Services;

use App\Models\Tournament\Tournament;
use App\Models\Tournament\Team;
use App\Models\Tournament\Category;
use Illuminate\Support\Facades\DB;
use Exception;

class TournamentService
{
    // =========================================================================
    // CRUD TORNEIOS
    // =========================================================================

    public function getAllTournaments()
    {
        return Tournament::with(['status', 'categories'])->paginate(10);
    }

    public function getTournamentById($id)
    {
        return Tournament::with(['status', 'categories', 'placeGroup'])->findOrFail($id);
    }

    public function createTournament(array $data)
    {
        return DB::transaction(function () use ($data) {
            return Tournament::create($data);
        });
    }

    public function updateTournament($id, array $data)
    {
        return DB::transaction(function () use ($id, $data) {
            $tournament = Tournament::findOrFail($id);
            $tournament->update($data);
            return $tournament;
        });
    }

    public function deleteTournament($id)
    {
        return DB::transaction(function () use ($id) {
            $tournament = Tournament::findOrFail($id);
            $tournament->delete();
            return true;
        });
    }

    // =========================================================================
    // CRUD TIMES (TEAMS)
    // =========================================================================

    public function getAllTeams()
    {
        return Team::with('members')->paginate(15);
    }

    public function createTeam(array $data)
    {
        return Team::create($data);
    }

    public function updateTeam($id, array $data)
    {
        $team = Team::findOrFail($id);
        $team->update($data);
        return $team;
    }

    public function deleteTeam($id)
    {
        $team = Team::findOrFail($id);
        return $team->delete();
    }

    // =========================================================================
    // CRUD CATEGORIAS
    // =========================================================================

    public function getAllCategories()
    {
        return Category::all();
    }

    public function createCategory(array $data)
    {
        return Category::create($data);
    }

    public function updateCategory($id, array $data)
    {
        $category = Category::findOrFail($id);
        $category->update($data);
        return $category;
    }

    public function deleteCategory($id)
    {
        $category = Category::findOrFail($id);
        return $category->delete();
    }
}