<?php

namespace App\Http\Controllers\Placar\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Placar\EquipeResource;
use App\Models\Placar\Equipe;
use Illuminate\Http\Request;

class EquipeController extends Controller
{
    /** GET /placar/equipes?busca=&modalidade=futsal&page= */
    public function index(Request $request)
    {
        $equipes = Equipe::query()
            ->ativas()
            ->withCount('times')
            ->busca($request->query('busca'))
            ->when($request->filled('modalidade'), function ($query) use ($request) {
                $query->whereHas('times', function ($times) use ($request) {
                    $times->daModalidade($request->query('modalidade'));
                });
            })
            ->orderBy('nome')
            ->paginate(20);

        return EquipeResource::collection($equipes);
    }
}
