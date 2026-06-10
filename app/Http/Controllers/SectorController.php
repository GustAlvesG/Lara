<?php

namespace App\Http\Controllers;

use App\Models\Sector;
use App\Models\User;
use Illuminate\Http\Request;

class SectorController extends Controller
{
    public function index()
    {
        $sectors = Sector::withCount('users')->orderBy('name')->get();
        return view('sector.index', compact('sectors'));
    }

    public function create()
    {
        return view('sector.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:255|unique:sectors,name',
            'description' => 'nullable|string|max:500',
        ]);

        $sector = Sector::create([
            'name'        => $request->input('name'),
            'description' => $request->input('description'),
        ]);

        return redirect()->route('sectors.show', $sector->id)
            ->with('success', 'Setor "' . $sector->name . '" criado com sucesso.');
    }

    public function show(Sector $id)
    {
        $sector = $id;
        $sector->load('users');
        $users = User::orderBy('name')->get();

        return view('sector.show', compact('sector', 'users'));
    }

    public function update(Request $request, Sector $id)
    {
        $sector = $id;

        $request->validate([
            'name'        => 'required|string|max:255|unique:sectors,name,' . $sector->id,
            'description' => 'nullable|string|max:500',
        ]);

        $sector->update([
            'name'        => $request->input('name'),
            'description' => $request->input('description'),
        ]);

        return redirect()->route('sectors.show', $sector->id)
            ->with('success', 'Setor "' . $sector->name . '" atualizado com sucesso.');
    }

    public function destroy(Sector $id)
    {
        $sector = $id;

        if ($sector->users()->count() > 0) {
            return redirect()->route('sectors.index')
                ->with('error', 'Não é possível excluir o setor "' . $sector->name . '" pois possui membros vinculados.');
        }

        $sectorName = $sector->name;
        $sector->delete();

        return redirect()->route('sectors.index')
            ->with('success', 'Setor "' . $sectorName . '" excluído com sucesso.');
    }

    public function addUser(Request $request, Sector $id)
    {
        $sector = $id;

        $request->validate([
            'user_id' => 'required|exists:users,id',
            'role'    => 'required|in:coordinator,collaborator',
        ]);

        $sector->users()->syncWithoutDetaching([
            $request->input('user_id') => ['role' => $request->input('role')],
        ]);

        return redirect()->route('sectors.show', $sector->id)
            ->with('success', 'Usuário adicionado ao setor com sucesso.');
    }

    public function removeUser(Sector $id, int $userId)
    {
        $sector = $id;
        $sector->users()->detach($userId);

        return redirect()->route('sectors.show', $sector->id)
            ->with('success', 'Usuário removido do setor com sucesso.');
    }
}
