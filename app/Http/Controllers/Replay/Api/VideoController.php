<?php

namespace App\Http\Controllers\Replay\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Replay\StoreVideoRequest;
use App\Models\Replay\Camera;
use App\Services\Replay\RemoteStorageUnavailable;
use App\Services\Replay\VideoIntakeService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

/**
 * Recepção dos clipes gravados nas quadras.
 *
 * O sistema de captura grava o clipe no servidor de arquivos (FTP) e avisa
 * por aqui, com o caminho e os metadados. O Lara confere o arquivo no FTP,
 * traz a cópia que vai servir e amarra à reserva. O arquivo não trafega pela
 * API — o limite de upload do PHP deixou de importar.
 *
 * FTP fora do ar é 503, não 422: o aviso está certo e deve ser repetido.
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
                (string) $request->input('path'),
                Carbon::parse($request->input('recorded_at')),
                (int) $request->input('duration_seconds'),
                $request->input('external_id'),
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (RemoteStorageUnavailable $e) {
            return response()->json(['message' => 'Servidor de arquivos (FTP) indisponível. Tente de novo.'], 503);
        }

        $video = $result['video'];

        return response()->json([
            'uuid' => $video->uuid,
            'url' => $video->url(),
            'path' => $video->file_path,
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
        // 200 no reenvio do mesmo clipe: a requisição está correta, o
        // recurso já existia. 201 só quando algo novo foi criado.
        ], $result['duplicated'] ? 200 : 201);
    }
}
