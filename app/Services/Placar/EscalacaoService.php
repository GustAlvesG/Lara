<?php

namespace App\Services\Placar;

use App\Models\Placar\Escalacao;
use App\Models\Placar\Jogo;
use Illuminate\Support\Facades\DB;

/**
 * Grava a escalação de um time num jogo — usado pelo endpoint da API
 * (POST /placar/jogos/{jogo}/escalacao) e pela tela web equivalente. Único
 * ponto de verdade da regra: substitui por completo (apaga a escalação
 * anterior do time e grava a nova), nunca faz merge.
 */
class EscalacaoService
{
    /**
     * @param  array<int, array{jogador_id: int, numero: string, titular?: bool, capitao?: bool}>  $jogadores
     * @return \Illuminate\Support\Collection<int, Escalacao>
     */
    public function substituir(Jogo $jogo, int $timeId, array $jogadores): \Illuminate\Support\Collection
    {
        DB::transaction(function () use ($jogo, $timeId, $jogadores) {
            $jogo->escalacoes()->where('time_id', $timeId)->delete();

            if ($jogadores === []) {
                return;
            }

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

        return $jogo->escalacoes()->where('time_id', $timeId)->with('jogador')->get();
    }
}
