<?php

namespace App\Jobs;

use App\Models\UberAccessRequestMessage;
use App\Services\Poli\PoliMessageParser;
use App\Services\UberAccessRequestFlow;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessUberAccessRequestMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $uberAccessRequestMessageId) {}

    public function handle(PoliMessageParser $parser, UberAccessRequestFlow $flow): void
    {
        $messageRow = UberAccessRequestMessage::find($this->uberAccessRequestMessageId);
        if (!$messageRow) {
            return;
        }

        $payload = $messageRow->raw_payload;

        if (!$parser->isRelevantEvent($payload)) {
            return;
        }

        try {
            $parsed = $parser->parse($payload);
            if (!$parsed) {
                return;
            }

            $uberAccessRequest = $flow->handle($parsed);

            if ($uberAccessRequest) {
                $messageRow->update(['uber_access_request_id' => $uberAccessRequest->id]);
            }
        } catch (\Throwable $e) {
            Log::error('ProcessUberAccessRequestMessage: falha ao processar mensagem', [
                'uber_access_request_message_id' => $this->uberAccessRequestMessageId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
