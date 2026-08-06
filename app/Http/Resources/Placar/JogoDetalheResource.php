<?php

namespace App\Http\Resources\Placar;

use App\Models\Placar\Time;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Payload COMPLETO de um jogo (GET /placar/jogos/{jogo}) — pronto para o
 * Node montar o gameState de uma vez, sem chamada extra. Por time, usa a
 * escalação do jogo se existir; senão cai para o elenco da temporada
 * corrente (ver Jogo::elencoOperacionalDoTime()).
 */
class JogoDetalheResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'jogo' => [
                'id' => $this->id,
                'esporte' => $this->modalidade->slug,
                'status' => $this->status,
                'data_hora' => $this->data_hora?->toIso8601String(),
                'local' => $this->local,
                'competicao' => $this->competicao ? [
                    'id' => $this->competicao->id,
                    'nome' => $this->competicao->nome,
                ] : null,
            ],
            'time_casa' => $this->dadosDoTime($this->timeCasa),
            'time_fora' => $this->dadosDoTime($this->timeFora),
        ];
    }

    private function dadosDoTime(Time $time): array
    {
        return [
            'id' => $time->id,
            'nome_exibicao' => $time->nomeExibicaoResolvido(),
            'logo_url' => $time->logoUrl(),
            'elenco' => $this->resource->elencoOperacionalDoTime($time)
                ->map(fn (array $item) => [
                    'jogador_id' => $item['jogador']->id,
                    'numero' => $item['numero'],
                    'nome_exibicao' => $item['jogador']->nomeExibicaoResolvido(),
                    'foto_url' => $item['jogador']->fotoUrl(),
                    'titular' => (bool) $item['titular'],
                    'capitao' => (bool) $item['capitao'],
                ])
                ->values(),
        ];
    }
}
