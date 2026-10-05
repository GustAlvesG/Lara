<?php

namespace App\Http\Controllers\Replay\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Replay\StoreVideoRequest;
use App\Models\Replay\Camera;
use App\Services\Replay\VideoIntakeService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

/**
 * Recepção dos clipes gravados nas quadras.
 *
 * O clipe chega por POST multipart — uma requisição, sem FTP, sem pasta
 * compartilhada e sem o Lara precisar alcançar a rede das quadras. No volume
 * do clube (50 a 100 clipes/dia) isso é de longe o arranjo mais simples que
 * funciona.
 *
 * Atenção de operação: o teto real do upload é o `post_max_size` /
 * `upload_max_filesize` do PHP. Com os 30M do servidor, um clipe de 60s a
 * 1080p é recusado ANTES de chegar aqui — o Laravel recebe um corpo vazio e
 * devolve 422 sem explicação útil. Ver docs/replay-api.md.
 */
class VideoController extends Controller
{
    public function __construct(private VideoIntakeService $intake)
    {
    }

    public function store(StoreVideoRequest $request, string $externalId): JsonResponse
    {
        $camera = Camera::active()->with('place')->where('external_id', $externalId)->first();

        if (! $camera) {
            return response()->json(['message' => 'Câmera não encontrada ou inativa.'], 404);
        }

        try {
            $result = $this->intake->store(
                $camera,
                $request->file('file'),
                Carbon::parse($request->input('recorded_at')),
                (int) $request->input('duration_seconds'),
                $request->input('external_id'),
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $video = $result['video'];

        return response()->json([
            'uuid' => $video->uuid,
            'url' => $video->url(),
            'place' => [
                'id' => $video->place_id,
                'name' => $video->place?->name,
            ],
            'recorded_at' => $video->recorded_at?->toIso8601String(),
            'expires_at' => $video->expires_at?->toIso8601String(),
            // Diz ao outro lado se o vídeo caiu no colo de um sócio. Serve
            // para o log dele; nenhum dado do sócio sai por aqui.
            'linked_to_member' => $video->member_id !== null,
            'duplicated' => $result['duplicated'],
        // 200 no reenvio do mesmo external_id: a requisição está correta, o
        // recurso já existia. 201 só quando algo novo foi criado.
        ], $result['duplicated'] ? 200 : 201);
    }
}
