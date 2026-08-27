<?php

namespace App\Http\Resources\Placar;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Um jogador dentro do elenco de um time, para
 * GET /placar/times/{time}/elenco. Recebe um Elenco (não um Jogador) — o
 * número e a posição são do vínculo, não do jogador em si.
 */
class TimeElencoJogadorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'jogador_id' => $this->jogador_id,
            'nome_exibicao' => $this->jogador->nomeExibicaoResolvido(),
            'numero' => $this->numero,
            'posicao' => $this->posicao,
            'foto_url' => $this->jogador->fotoUrl(),
            'video_url' => $this->jogador->videoUrl(),
        ];
    }
}
