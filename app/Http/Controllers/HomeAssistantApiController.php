<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreHomeAssistantManualCommandRequest;
use App\Models\Contactor;
use App\Models\HomeAssistantOverride;
use App\Services\HomeAssistant\ManualCommandService;
use Illuminate\Http\JsonResponse;

/**
 * Escrita de comando manual pelo Home Assistant (origem típica: Telegram).
 *
 * Contraparte de `GET /api/schedule/home-assistant/automation`, que só lê. O HA
 * grava aqui em vez de acionar o switch por conta própria: assim a Lara continua
 * sendo a única fonte da verdade e o polling seguinte não desfaz o comando.
 *
 * O `on` da resposta é o estado recalculado depois da escrita, não o que foi
 * pedido — um "liga" perde para um agendamento de prioridade maior.
 */
class HomeAssistantApiController extends Controller
{
    public function __construct(private ManualCommandService $commands)
    {
    }

    public function manual(StoreHomeAssistantManualCommandRequest $request, string $entity_id): JsonResponse
    {
        // Pelo entity_id, que é o que o HA conhece — o id numérico é interno.
        $contactor = Contactor::where('entity_id', $entity_id)->first()
            ?? abort(404, 'Contator não encontrado.');

        if ($request->validated('state') === 'auto') {
            $this->commands->clear($contactor);

            return response()->json($this->payload($contactor, null), 200);
        }

        $override = $this->commands->apply(
            $contactor,
            $request->validated('state'),
            $request->durationMinutes(),
            $request->validated('origin'),
        );

        return response()->json($this->payload($contactor, $override), 201);
    }

    /** @return array<string, mixed> */
    private function payload(Contactor $contactor, ?HomeAssistantOverride $override): array
    {
        return [
            'entity_id'    => $contactor->entity_id,
            'on'           => $this->commands->currentState($contactor)->on,
            'manual_until' => $override?->expires_at?->toIso8601String(),
            'override_id'  => $override?->id,
        ];
    }
}
