<?php

namespace App\Http\Controllers\Placar\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Placar\AtualizarEscalacaoRequest;
use App\Models\Placar\Escalacao;
use App\Models\Placar\Jogo;
use Illuminate\Support\Facades\DB;

class EscalacaoController extends Controller
{
    /**
     * POST /placar/jogos/{jogo}/escalacao
     * body: { time_id, jogadores: [{ jogador_id, numero, titular?, capitao? }] }
     *
     * Grava/substitui a escalação daquele time neste jogo — usado quando o
     * operador ajusta o elenco no controle antes de começar. Substituição
     * completa (apaga a escalação anterior do time e grava a nova), não
     * merge: é o que "substitui" no enunciado pede.
     */
    public function store(AtualizarEscalacaoRequest $request, Jogo $jogo)
    {
        $timeId = (int) $request->input('time_id');
        $jogadores = $request->input('jogadores');

        DB::transaction(function () use ($jogo, $timeId, $jogadores) {
            $jogo->escalacoes()->where('time_id', $timeId)->delete();

            $agora = now();
            Escalacao::insert(array_map(fn (array $item) => [
                'jogo_id' => $jogo->id,
                'time_id' => $timeId,
                'jogador_id' => $item['jogador_id'],
                'numero' => $item['numero'],
                'titular' => $item['titular'] ?? false,
                'capitao' => $item['capitao'] ?? false,
                'created_at' => $agora,
                'updated_at' => $agora,
            ], $jogadores));
        });

        $escalacao = $jogo->escalacoes()->where('time_id', $timeId)->with('jogador')->get();

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
