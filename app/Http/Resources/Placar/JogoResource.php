<?php

namespace App\Http\Resources\Placar;

use App\Models\Placar\Time;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Item da lista de jogos (GET /placar/jogos) — a tela de seleção do Node.
 * Para o payload completo de um jogo, ver JogoDetalheResource.
 */
class JogoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'esporte' => $this->modalidade->slug,
            'status' => $this->status,
            'data_hora' => $this->data_hora?->toIso8601String(),
            'local' => $this->local,
            'competicao' => $this->competicao ? [
                'id' => $this->competicao->id,
                'nome' => $this->competicao->nome,
            ] : null,
            'time_casa' => $this->dadosDoTime($this->timeCasa),
            'time_fora' => $this->dadosDoTime($this->timeFora),
            'criado_em_campo' => (bool) $this->criado_em_campo,
        ];
    }

    /**
     * Além do nome de exibição, a equipe e a categoria: na tela de seleção
     * do Node, "CF Adulto" e "CF Sub-15" só se distinguem por elas, e o
     * operador precisa acertar o jogo antes de começar.
     */
    private function dadosDoTime(Time $time): array
    {
        return [
            'id' => $time->id,
            'nome_exibicao' => $time->nomeExibicaoResolvido(),
            'categoria' => $time->categoria,
            'equipe' => [
                'id' => $time->equipe->id,
                'nome' => $time->equipe->nome,
                'nome_curto' => $time->equipe->nome_curto,
            ],
            'logo_url' => $time->logoUrl(),
        ];
    }
}
