<?php

namespace App\Http\Resources\Replay;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Um clipe, como o site de locação o enxerga.
 *
 * A galeria da quadra é aberta a qualquer visitante, então aqui NÃO sai nada
 * que identifique o sócio: `has_member` diz apenas que o vídeo foi gravado
 * durante uma reserva paga — que é o que a tela precisa para marcar "vídeo de
 * reserva" — sem dizer de quem.
 *
 * A `url` é o arquivo estático, servido direto pelo servidor web. É o que dá
 * range request, e com ele a possibilidade de avançar o vídeo no player em
 * vez de assistir do começo.
 *
 * @property-read \App\Models\Replay\Video $resource
 */
class VideoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'url' => $this->url(),
            'place' => [
                'id' => $this->place_id,
                'name' => $this->whenLoaded('place', fn () => $this->place->name),
            ],
            'place_group' => [
                'id' => $this->place_group_id,
                'name' => $this->whenLoaded('group', fn () => $this->group?->name),
            ],
            'orientation' => $this->orientation,
            'duration_seconds' => $this->duration_seconds,
            'size_bytes' => $this->size_bytes,
            'recorded_at' => $this->recorded_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'days_left' => $this->daysLeft(),
            'has_member' => $this->member_id !== null,
        ];
    }
}
