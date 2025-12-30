<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $users = User::with('roles.permissions')->get();

        $users = $users->sortBy('name');

        return view('user.index', compact('users'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $id)
    {
        $user = $id;
        $user->load('roles');
        $roles = \Spatie\Permission\Models\Role::all();

        return view('user.edit', compact('user', 'roles'));
    }

    /**
     * Update the specified resource     in storage.
     */
    public function update(Request $request, User $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'status' => 'required|in:1,2',
            'role_id' => 'required|exists:roles,id',
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        $user = $id;

        $user->name = $request->input('name');
        if ($request->filled('password')) {
            $user->password = bcrypt($request->input('password'));
        } else {
            // Mantém a senha atual se nenhum valor for fornecido
            $user->password = $user->password;
        }
        $user->email = $request->input('email');
        $user->status_id = $request->input('status');
        $user->save();


        // Atualiza o papel do usuário
        $role = \Spatie\Permission\Models\Role::findById($request->input('role_id'));
        $user->syncRoles([$role]);

        return redirect()->route('users.index')->with('success', 'Usuário atualizado com sucesso.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        
    }
}
