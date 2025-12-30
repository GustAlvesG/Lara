<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

class VideoWallController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('videowall.index');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    public function test()
    {
        // Criar role e atribuir permissão existente
        $user = User::find(1);

        // Atribuir uma role
        $user->assignRole('writer');

        //Remover uma role
        $user->removeRole('writer');
        return 'Test route is working!';
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
