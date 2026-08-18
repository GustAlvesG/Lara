<?php

namespace App\Http\Controllers\Placar\Api;

use App\Http\Controllers\Controller;
use App\Models\Placar\Jogador;
use App\Models\Placar\Jogo;
use App\Services\Placar\ScoutService;
use Illuminate\Http\Request;

/**
 * Camada de leitura do scout — súmula do jogo, ficha de atuação de um
 * jogador numa partida, e as partidas de um jogador. Toda a agregação mora
 * em ScoutService, reaproveitada pelas telas web. Aqui só filtro de request
 * e resposta.
 *
 * Não existe ranking de artilharia: o scout mede atuação por partida.
 */
class ScoutController extends Controller
{
    /** GET /placar/jogos/{jogo}/sumula */
    public function sumula(Jogo $jogo, ScoutService $scout)
    {
        return response()->json($scout->sumula($jogo));
    }

    /**
     * GET /placar/jogos/{jogo}/jogadores/{jogador}/atuacao
     * Ficha minutada do jogador nesta partida.
     */
    public function atuacao(Jogo $jogo, Jogador $jogador, ScoutService $scout)
    {
        return response()->json($scout->atuacaoNaPartida($jogo, $jogador));
    }

    /** GET /placar/scout/jogadores/{jogador}?temporada= */
    public function jogador(Request $request, Jogador $jogador, ScoutService $scout)
    {
        $temporada = $request->query('temporada');

        return response()->json($scout->partidasDoJogador($jogador, $temporada ? (int) $temporada : null));
    }
}
