<?php

namespace App\Http\Controllers\Replay\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Replay\CameraConfigResource;
use App\Models\Replay\Camera;
use App\Services\Replay\ReplayResolver;
use Illuminate\Http\JsonResponse;

/**
 * O que o sistema de captura consulta para saber como gravar.
 *
 * Integração por PULL, e não por push: o Lara é a fonte de verdade e o outro
 * sistema busca quando quer. Um push dependeria de o servidor de captura
 * estar de pé no exato momento em que alguém salva uma configuração — e uma
 * mudança perdida só apareceria no vídeo, depois do jogo.
 *
 * `config_hash` é o atalho que torna o pull barato: o sistema de captura
 * guarda o valor e só reprocessa (baixar overlays, reconfigurar câmeras)
 * quando ele muda.
 */
class CameraController extends Controller
{
    public function __construct(private ReplayResolver $resolver)
    {
    }

    /** Todas as câmeras ATIVAS, com a configuração já resolvida. */
    public function index(): JsonResponse
    {
        $cameras = Camera::active()
            ->with('place.group')
            ->orderBy('external_id')
            ->get()
            // Câmera cuja quadra foi apagada não tem o que configurar — e
            // devolvê-la faria o outro lado gravar sem destino.
            ->filter(fn (Camera $camera) => $camera->place !== null)
            ->values();

        foreach ($cameras as $camera) {
            $camera->resolved_config = $this->resolver->resolveFor($camera->place);
        }

        return response()->json([
            'config_hash' => $this->resolver->configHash(),
            'cameras' => CameraConfigResource::collection($cameras)->resolve(),
        ]);
    }

    /** Uma câmera só — para o equipamento que já sabe quem é. */
    public function show(string $externalId): JsonResponse
    {
        $camera = Camera::active()->with('place.group')->where('external_id', $externalId)->first();

        if (! $camera || ! $camera->place) {
            return response()->json(['message' => 'Câmera não encontrada ou inativa.'], 404);
        }

        $camera->resolved_config = $this->resolver->resolveFor($camera->place);

        return response()->json([
            'config_hash' => $this->resolver->configHash(),
            'camera' => (new CameraConfigResource($camera))->resolve(),
        ]);
    }

    /**
     * Sinal de vida do equipamento.
     *
     * Existe para a tela de câmeras denunciar quem parou de falar — sem ele,
     * o primeiro a notar uma câmera morta seria o sócio que apertou o botão e
     * não recebeu vídeo.
     *
     * Grava com `timestamps = false` de propósito: o `updated_at` da câmera
     * entra no config_hash, e um heartbeat de minuto em minuto faria o parque
     * inteiro achar que a configuração mudou e rebaixar todos os overlays.
     * Telemetria não é configuração.
     */
    public function heartbeat(string $externalId): JsonResponse
    {
        $camera = Camera::where('external_id', $externalId)->first();

        if (! $camera) {
            return response()->json(['message' => 'Câmera não encontrada.'], 404);
        }

        $camera->timestamps = false;
        $camera->last_seen_at = now();
        $camera->save();

        return response()->json([
            'ok' => true,
            'config_hash' => $this->resolver->configHash(),
        ]);
    }
}
