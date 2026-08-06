<?php

namespace App\Http\Controllers\Placar\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Placar\TimeElencoJogadorResource;
use App\Http\Resources\Placar\TimeResource;
use App\Models\Placar\Time;
use Illuminate\Http\Request;

class TimeController extends Controller
{
    /**
     * GET /placar/times?modalidade=basquete&equipe_id=&categoria=&busca=
     * Para o operador escolher no modo avulso — sem paginação, a lista já
     * sai filtrada o suficiente para caber numa tela.
     */
    public function index(Request $request)
    {
        $times = Time::query()
            ->ativos()
            ->with(['equipe', 'modalidade'])
            ->daModalidade($request->query('modalidade'))
            ->when($request->filled('equipe_id'), fn ($query) => $query->where('equipe_id', $request->query('equipe_id')))
            ->when($request->filled('categoria'), fn ($query) => $query->where('categoria', $request->query('categoria')))
            ->busca($request->query('busca'))
            ->orderBy('categoria')
            ->get();

        return TimeResource::collection($times);
    }

    /** GET /placar/times/{time}/elenco?temporada=2026 */
    public function elenco(Request $request, Time $time)
    {
        $elencos = $time->elencos()
            ->daTemporada($request->query('temporada'))
            ->where('ativo', true)
            ->with('jogador')
            ->get();

        return TimeElencoJogadorResource::collection($elencos);
    }
}
