<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with('roles.permissions')->get()->sortBy('name');
        return view('user.index', compact('users'));
    }

    public function create()
    {
        $roles = Role::orderBy('name')->get();
        return view('user.create', compact('roles'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'       => 'required|string|max:255',
            'email'      => 'required|string|email|max:255|unique:users,email',
            'matricula'  => 'nullable|string|max:5|unique:users,matricula',
            'password'   => 'required|string|min:8|confirmed',
            'pin'        => 'nullable|digits:6',
            'role_id'    => 'required|exists:roles,id',
            'status'     => 'nullable|in:1,2',
        ]);

        $user = User::create([
            'name'      => $request->input('name'),
            'email'     => $request->input('email'),
            'matricula' => $request->input('matricula') ?: null,
            'password'  => Hash::make($request->input('password')),
            // Cast 'hashed' no model aplica o hash automaticamente.
            'pin'       => $request->filled('pin') ? $request->input('pin') : null,
            'status_id' => $request->input('status', 1),
        ]);

        $role = Role::findById($request->input('role_id'));
        $user->assignRole($role);

        return redirect()->route('users.index')
            ->with('success', 'Usuário "' . $user->name . '" criado com sucesso.');
    }

    public function edit(User $id)
    {
        $user = $id;
        $user->load('roles');
        $roles = Role::orderBy('name')->get();

        return view('user.edit', compact('user', 'roles'));
    }

    public function update(Request $request, User $id)
    {
        $user = $id;

        $request->validate([
            'name'      => 'required|string|max:255',
            'email'     => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'matricula' => 'nullable|string|max:5|unique:users,matricula,' . $user->id,
            'status'    => 'required|in:1,2',
            'role_id'   => 'required|exists:roles,id',
            'password'  => 'nullable|string|min:8|confirmed',
            'pin'       => 'nullable|digits:6',
        ]);

        $user->name      = $request->input('name');
        $user->email     = $request->input('email');
        $user->matricula = $request->input('matricula') ?: null;
        $user->status_id = $request->input('status');

        if ($request->filled('password')) {
            $user->password = Hash::make($request->input('password'));
        }

        // PIN em branco mantém o atual; enviar 6 dígitos redefine (cast 'hashed').
        if ($request->filled('pin')) {
            $user->pin = $request->input('pin');
        }

        $user->save();

        $role = Role::findById($request->input('role_id'));
        $user->syncRoles([$role]);

        return redirect()->route('users.index')
            ->with('success', 'Usuário "' . $user->name . '" atualizado com sucesso.');
    }

    public function destroy(User $id)
    {
        $user = $id;

        if ($user->id === auth()->id()) {
            return redirect()->route('users.index')
                ->with('error', 'Você não pode excluir sua própria conta.');
        }

        $userName = $user->name;
        $user->delete();

        return redirect()->route('users.index')
            ->with('success', 'Usuário "' . $userName . '" excluído com sucesso.');
    }
}
