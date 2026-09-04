<?php

namespace App\Http\Controllers\Placar\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Placar\AtualizarEscalacaoRequest;
use App\Models\Placar\Escalacao;
use App\Models\Placar\Jogo;
use App\Services\Placar\EscalacaoService;

class EscalacaoController extends Controller
{
    /**
     * POST /placar/jogos/{jogo}/escalacao
     * body: { time_id, jogadores: [{ jogador_id, numero, titular?, capitao? }] }
     *
     * Grava/substitui a escalação daquele time neste jogo — usado quando o
     * operador ajusta o elenco no controle antes de começar. A regra
     * (substituição completa, não merge) mora em EscalacaoService,
     * reaproveitada pela tela web equivalente.
     */
    public function store(AtualizarEscalacaoRequest $request, Jogo $jogo, EscalacaoService $escalacoes)
    {
        $timeId = (int) $request->input('time_id');

        $escalacao = $escalacoes->substituir($jogo, $timeId, $request->input('jogadores'));

        return response()->json([
            'time_id' => $timeId,
            'jogadores' => $escalacao->map(fn (Escalacao $item) => [
                'jogador_id' => $item->jogador_id,
                'nome_exibicao' => $item->jogador->nomeExibicaoResolvido(),
                'numero' => $item->numero,
                'titular' => (bool) $item->titular,
                'capitao' => (bool) $item->capitao,
            ]),
        ]);
    }
}
