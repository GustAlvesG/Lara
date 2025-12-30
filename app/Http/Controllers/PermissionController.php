<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $roles = Role::with('permissions')->get();
        // $permissions = Permission::all();
        return view('permission.index', compact('roles'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
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
    public function show(string $id)
    {
        $role = Role::with('permissions')->find($id);
        $allPermissions = Permission::all();
        $selectedPermissions = $role->permissions->pluck('id')->toArray();
        // return [
        //     'role' => $role,
        //     'allPermissions' => $allPermissions,
        //     'selectedPermissions' => $role->permissions->pluck('id')->toArray(),
        // ];
        return view('permission.show', compact('role', 'allPermissions', 'selectedPermissions'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $role = Role::find($id);
        $role->name = $request->input('name');
        $role->save();

        $permissions = $request->input('permissions', []);
        // dd($permissions);
        $role->syncPermissions($permissions);

        return redirect()->route('roles-permission.index')->with('success', 'Role updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
