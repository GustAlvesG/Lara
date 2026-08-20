<?php

namespace App\Http\Resources\Placar;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EquipeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'nome_curto' => $this->nome_curto,
            'cidade' => $this->cidade,
            'logo_url' => $this->logoUrl(),
            // Só presente quando a listagem veio com withCount('times').
            'times_count' => $this->when(isset($this->times_count), fn () => $this->times_count),
            'criado_em_campo' => (bool) $this->criado_em_campo,
            'ativo' => (bool) $this->ativo,
        ];
    }
}
