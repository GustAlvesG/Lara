<?php

namespace App\Http\Resources\Replay;

use App\Support\Replay\Orientation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * O que uma câmera precisa saber para gravar: orientação, duração do clipe e
 * o overlay já composto.
 *
 * A herança (quadra > esporte > padrão) já vem resolvida pelo ReplayResolver
 * — o sistema de captura nunca decide nada, só obedece. `*_source` está aqui
 * para diagnóstico: quando alguém questionar por que a quadra grava em pé, a
 * resposta está na própria resposta da API.
 *
 * As URLs são absolutas porque o consumidor é outro sistema, em outra
 * máquina: caminho relativo não significa nada para ele.
 *
 * @property-read \App\Models\Replay\Camera $resource
 */
class CameraConfigResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $camera = $this->resource;

        // `resolved_config` é posto pelo controller (saída do ReplayResolver)
        // e vive só em memória: resolver aqui dentro faria uma consulta por
        // câmera na listagem, que é o endpoint mais chamado da API.
        $config = $camera->resolved_config;
        $layout = $config['layout'] ?? null;
        $dimensions = Orientation::dimensions($config['orientation']);

        return [
            'external_id' => $camera->external_id,
            'name' => $camera->name,
            'position' => $camera->position,
            'place' => [
                'id' => $camera->place?->id,
                'name' => $camera->place?->name,
            ],
            'place_group' => [
                'id' => $camera->place?->group?->id,
                'name' => $camera->place?->group?->name,
            ],
            'orientation' => $config['orientation'],
            // Sempre os segundos ANTERIORES ao aperto do botão. Não há
            // pós-roll no contrato.
            'clip_seconds' => $config['clip_seconds'],
            'overlay' => $layout ? [
                'png_url' => $layout->overlayUrl(),
                // Só existe quando o layout tem GIF animado E o servidor tem
                // ffmpeg. Quem consome deve tratar null como "use o PNG".
                'animated_url' => $layout->animatedOverlayUrl(),
                'width' => $dimensions['width'],
                'height' => $dimensions['height'],
                'hash' => $layout->overlay_hash,
                'updated_at' => $layout->overlay_rendered_at?->toIso8601String(),
            ] : null,
            'sources' => [
                'settings' => $config['setting_source'],
                'layout' => $config['layout_source'],
            ],
        ];
    }
}
