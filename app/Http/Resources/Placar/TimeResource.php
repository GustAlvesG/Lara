<?php

namespace App\Http\Resources\Placar;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TimeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome_exibicao' => $this->nomeExibicaoResolvido(),
            'equipe' => [
                'id' => $this->equipe->id,
                'nome' => $this->equipe->nome,
            ],
            'categoria' => $this->categoria,
            'modalidade' => [
                'id' => $this->modalidade->id,
                'slug' => $this->modalidade->slug,
            ],
            // Própria, senão herda da equipe — resolvido em Time::logoUrl().
            'logo_url' => $this->logoUrl(),
            'criado_em_campo' => (bool) $this->criado_em_campo,
            'ativo' => (bool) $this->ativo,
        ];
    }
}
