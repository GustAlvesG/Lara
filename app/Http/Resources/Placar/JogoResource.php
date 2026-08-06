<?php

namespace App\Http\Resources\Placar;

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
            'time_casa' => [
                'id' => $this->timeCasa->id,
                'nome_exibicao' => $this->timeCasa->nomeExibicaoResolvido(),
                'logo_url' => $this->timeCasa->logoUrl(),
            ],
            'time_fora' => [
                'id' => $this->timeFora->id,
                'nome_exibicao' => $this->timeFora->nomeExibicaoResolvido(),
                'logo_url' => $this->timeFora->logoUrl(),
            ],
            'criado_em_campo' => (bool) $this->criado_em_campo,
        ];
    }
}
