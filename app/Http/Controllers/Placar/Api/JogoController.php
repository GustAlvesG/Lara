<?php

namespace App\Http\Controllers\Placar\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Placar\JogoDetalheResource;
use App\Http\Resources\Placar\JogoResource;
use App\Models\Placar\Jogo;
use Illuminate\Http\Request;

class JogoController extends Controller
{
    /**
     * GET /placar/jogos?status=agendado&data=2026-08-06&modalidade=&competicao_id=
     * Default (sem `status`/`data`): jogos de hoje + amanhã, agendado ou ao_vivo.
     */
    public function index(Request $request)
    {
        $query = Jogo::query()->with(['modalidade', 'competicao', 'timeCasa', 'timeFora']);

        if ($request->filled('status')) {
            $query->statusEntre(explode(',', $request->query('status')));
        } else {
            $query->statusEntre([Jogo::STATUS_AGENDADO, Jogo::STATUS_AO_VIVO]);
        }

        if ($request->filled('data')) {
            $query->whereDate('data_hora', $request->query('data'));
        } else {
            $query->whereBetween('data_hora', [now()->startOfDay(), now()->addDay()->endOfDay()]);
        }

        $query->daModalidade($request->query('modalidade'));

        if ($request->filled('competicao_id')) {
            $query->where('competicao_id', $request->query('competicao_id'));
        }

        $jogos = $query->orderBy('data_hora')->paginate(20);

        return JogoResource::collection($jogos);
    }

    /** GET /placar/jogos/{jogo} — payload completo para o Node montar o gameState. */
    public function show(Jogo $jogo)
    {
        $jogo->load(['modalidade', 'competicao', 'timeCasa.equipe', 'timeFora.equipe']);

        return new JogoDetalheResource($jogo);
    }
}
