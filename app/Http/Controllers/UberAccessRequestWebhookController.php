<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessUberAccessRequestMessage;
use App\Models\UberAccessRequestMessage;
use App\Services\Poli\PoliMessageParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
}
