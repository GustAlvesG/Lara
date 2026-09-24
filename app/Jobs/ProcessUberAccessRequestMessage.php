<?php

namespace App\Jobs;

use App\Exceptions\PoliListMessageNotIndexedException;
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

    /**
     * Três tentativas por causa de uma corrida só: o toque pode ser
     * processado antes de o webhook do menu que o originou ter chegado. Fora
     * isso o job não repete — erro de processamento continua sendo logado e
     * descartado, como antes.
     */
    public $tries = 3;

    public function __construct(public int $uberAccessRequestMessageId) {}

    public function handle(PoliMessageParser $parser, UberAccessRequestFlow $flow): void
    {
        $messageRow = UberAccessRequestMessage::find($this->uberAccessRequestMessageId);
        if (!$messageRow) {
            return;
        }

        $payload = $messageRow->raw_payload;

        // Antes da checagem de relevância de propósito: o fim do atendimento é
        // anunciado pelas mensagens do bot (`event: "sent"`), que nunca entram
        // no fluxo e seriam descartadas aqui embaixo.
        $atendimentoEncerrado = $parser->extractFinishedAttendanceUuid($payload);

        if ($atendimentoEncerrado !== null) {
            CloseUberCaptureSession::dispatch($atendimentoEncerrado)
                ->delay(now()->addSeconds(UberAccessRequestFlow::CLOSURE_GRACE_SECONDS));
        }

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
        } catch (PoliListMessageNotIndexedException $e) {
            // Ainda dá tempo de o menu chegar: devolve para a fila. Esgotadas
            // as tentativas, o toque é recusado — é o que resta para um menu
            // que nunca foi indexado (por exemplo, enviado antes de o webhook
            // de saída ser ligado).
            if ($this->job && $this->attempts() < $this->tries) {
                $this->release(10);

                return;
            }

            Log::warning('ProcessUberAccessRequestMessage: gatilho recusado, menu nunca indexado', [
                'uber_access_request_message_id' => $this->uberAccessRequestMessageId,
                'poli_message_uuid' => $e->poliMessageUuid,
            ]);
        } catch (\Throwable $e) {
            Log::error('ProcessUberAccessRequestMessage: falha ao processar mensagem', [
                'uber_access_request_message_id' => $this->uberAccessRequestMessageId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
