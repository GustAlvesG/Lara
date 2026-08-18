<?php

namespace App\Http\Resources\Placar;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JogadorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'nome_exibicao' => $this->nomeExibicaoResolvido(),
            'foto_url' => $this->fotoUrl(),
            'video_url' => $this->videoUrl(),
            'data_nascimento' => $this->data_nascimento?->toDateString(),
            'documento' => $this->documento,
            'criado_em_campo' => (bool) $this->criado_em_campo,
            'ativo' => (bool) $this->ativo,
        ];
    }
}
