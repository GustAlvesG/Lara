<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessUberAccessRequestMessage;
use App\Models\PoliListMessage;
use App\Models\UberAccessRequestMessage;
use App\Services\Poli\PoliMessageParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UberAccessRequestWebhookController extends Controller
{
    public function handle(Request $request, PoliMessageParser $parser): JsonResponse
    {
        $payload = $request->all();

        if (!is_array($payload['value'] ?? null)) {
            return response()->json(['message' => 'Malformed payload'], 422);
        }

        $messageId = $parser->extractMessageId($payload);
        if ($messageId === null) {
            return response()->json(['message' => 'Malformed payload'], 422);
        }

        // Menu enviado: indexa AQUI, e não numa fila. A validação do gatilho
        // roda num job que pode ser processado logo depois do toque, e ela
        // precisa do menu já gravado — deixar as duas pontas na fila abriria
        // uma corrida que recusaria um toque legítimo.
        //
        // Antes da checagem de duplicidade de propósito: a Poli reenvia o
        // mesmo evento quando o `ack` muda, e a segunda volta não pode deixar
        // o índice pela metade. Por isso é upsert.
        $this->indexOutgoingList($payload, $parser);

        if (UberAccessRequestMessage::where('poli_message_id', $messageId)->exists()) {
            return response()->json(['status' => 'duplicate'], 200);
        }

        $messageRow = UberAccessRequestMessage::create([
            'poli_message_id' => $messageId,
            'raw_payload' => $payload,
        ]);

        ProcessUberAccessRequestMessage::dispatch($messageRow->id);

        return response()->json(['status' => 'accepted'], 200);
    }

    /**
     * Grava (ou atualiza) o menu de opções que acabou de sair.
     *
     * Falha aqui não derruba o webhook: perder o índice de um menu recusa um
     * gatilho, perder o webhook inteiro faz a Poli reenviar tudo. Entre os
     * dois, o barato é o primeiro — e fica no log.
     */
    private function indexOutgoingList(array $payload, PoliMessageParser $parser): void
    {
        if (!$parser->isOutgoingListMessage($payload)) {
            return;
        }

        $list = $parser->parseOutgoingList($payload);

        if ($list === null) {
            return;
        }

        try {
            PoliListMessage::updateOrCreate(
                ['poli_message_uuid' => $list['poli_message_uuid']],
                $list,
            );
        } catch (\Throwable $e) {
            Log::error('Poli: falha ao indexar menu enviado', [
                'poli_message_uuid' => $list['poli_message_uuid'],
                'error' => $e->getMessage(),
            ]);
        }
    }
}
