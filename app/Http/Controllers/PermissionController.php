<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionController extends Controller
{
    public function index()
    {
        $roles = Role::with('permissions')->get();
        return view('permission.index', compact('roles'));
    }

    public function create()
    {
        $allPermissions = Permission::orderBy('name')->get();
        return view('permission.create', compact('allPermissions'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        $role = Role::create(['name' => strtolower(trim($request->input('name')))]);
        $role->syncPermissions($request->input('permissions', []));

        return redirect()->route('roles-permission.index')
            ->with('success', 'Grupo "' . $role->name . '" criado com sucesso.');
    }

    public function show(string $id)
    {
        $role = Role::with('permissions')->findOrFail($id);
        $allPermissions = Permission::orderBy('name')->get();
        $selectedPermissions = $role->permissions->pluck('id')->toArray();

        return view('permission.show', compact('role', 'allPermissions', 'selectedPermissions'));
    }

    public function update(Request $request, string $id)
    {
        $role = Role::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255|unique:roles,name,' . $role->id,
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        $role->name = strtolower(trim($request->input('name')));
        $role->save();

        $role->syncPermissions($request->input('permissions', []));

        return redirect()->route('roles-permission.index')
            ->with('success', 'Grupo "' . $role->name . '" atualizado com sucesso.');
    }

    public function destroy(string $id)
    {
        $role = Role::findOrFail($id);

        if ($role->name === 'admin') {
            return redirect()->route('roles-permission.index')
                ->with('error', 'O grupo "admin" não pode ser excluído.');
        }

        $userCount = $role->users()->count();
        if ($userCount > 0) {
            return redirect()->route('roles-permission.index')
                ->with('error', 'O grupo "' . $role->name . '" possui ' . $userCount . ' usuário(s) vinculado(s) e não pode ser excluído.');
        }

        $roleName = $role->name;
        $role->delete();

        return redirect()->route('roles-permission.index')
            ->with('success', 'Grupo "' . $roleName . '" excluído com sucesso.');
    }
}
