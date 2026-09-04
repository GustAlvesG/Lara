<?php

namespace App\Http\Controllers\Placar\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Placar\EventosLoteRequest;
use App\Models\Placar\Jogo;
use App\Services\Placar\JogoEventoLoteService;

class JogoEventoController extends Controller
{
    /**
     * POST /placar/jogos/{jogo}/eventos — o endpoint mais importante da API.
     * body: { eventos: [ { uuid, sequencia, tipo, time_id?, jogador_id?,
     *                      valor?, periodo?, cronometro_ms?, ocorrido_em, payload? } ] }
     *
     * Toda a regra fica em JogoEventoLoteService — o controller só entrega
     * o lote e devolve a resposta no formato { aceitos, duplicados, rejeitados }.
     */
    public function store(EventosLoteRequest $request, Jogo $jogo, JogoEventoLoteService $service)
    {
        return response()->json($service->processar($jogo, $request->input('eventos')));
    }
}
