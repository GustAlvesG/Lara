<?php

namespace App\Http\Controllers\Placar\Api;

use App\Http\Controllers\Controller;
use App\Models\Placar\Jogador;
use App\Models\Placar\Jogo;
use App\Services\Placar\ScoutService;
use Illuminate\Http\Request;

/**
 * Camada de leitura do scout — súmula, artilharia, perfil do jogador. Toda
 * a agregação mora em ScoutService, reaproveitada pelas telas web na
 * Etapa 11. Aqui só filtro de request e resposta.
 */
class ScoutController extends Controller
{
    /** GET /placar/jogos/{jogo}/sumula */
    public function sumula(Jogo $jogo, ScoutService $scout)
    {
        return response()->json($scout->sumula($jogo));
    }

    /** GET /placar/scout/artilharia?modalidade=&competicao_id=&temporada=&time_id= */
    public function artilharia(Request $request, ScoutService $scout)
    {
        return response()->json($scout->artilharia([
            'modalidade' => $request->query('modalidade'),
            'competicao_id' => $request->query('competicao_id'),
            'temporada' => $request->query('temporada'),
            'time_id' => $request->query('time_id'),
        ]));
    }

    /** GET /placar/scout/jogadores/{jogador}?temporada= */
    public function jogador(Request $request, Jogador $jogador, ScoutService $scout)
    {
        $temporada = $request->query('temporada');

        return response()->json($scout->perfilJogador($jogador, $temporada ? (int) $temporada : null));
    }
}
