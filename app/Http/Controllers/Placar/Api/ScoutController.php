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
    /**
     * GET /placar/jogos/{jogo}/sumula?time_id=&periodo=
     *
     * Sem filtro, a súmula completa. Com `time_id`, a mesma súmula
     * recortada naquele time (`recorte` no corpo diz qual) — é o que cada
     * equipe leva embora. Com `periodo`, a da parcial (set/quarter/período):
     * linha do tempo e totais só do que aconteceu nela. Os dois se
     * combinam.
     *
     * `time_id` que não é de nenhum dos dois times do jogo é `422`:
     * devolver a completa em silêncio faria ela passar por recortada.
     */
    public function sumula(Request $request, Jogo $jogo, ScoutService $scout)
    {
        $timeId = $request->filled('time_id') ? (int) $request->query('time_id') : null;

        if ($timeId !== null && $jogo->ladoDoTime($timeId) === null) {
            return response()->json([
                'message' => 'O time informado não joga esta partida.',
                'errors' => ['time_id' => ['O time informado não joga esta partida.']],
            ], 422);
        }

        $periodo = $request->query('periodo');

        // Período inexistente na partida não é erro (parcial vazia é uma
        // resposta legítima), mas lixo no lugar do número é.
        if (filled($periodo) && (!ctype_digit((string) $periodo) || (int) $periodo < 1)) {
            return response()->json([
                'message' => 'O período precisa ser um número inteiro a partir de 1.',
                'errors' => ['periodo' => ['O período precisa ser um número inteiro a partir de 1.']],
            ], 422);
        }

        return response()->json($scout->sumula($jogo, $timeId, filled($periodo) ? (int) $periodo : null));
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
