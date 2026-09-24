<?php

namespace App\Http\Controllers;

use App\Jobs\CloseUberCaptureSession;
use App\Jobs\ProcessUberAccessRequestMessage;
use App\Models\PoliListMessage;
use App\Models\UberAccessRequestMessage;
use App\Services\Poli\PoliMessageParser;
use App\Services\UberAccessRequestFlow;
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

        // Só as mensagens de ENTRADA passam pelo fluxo, e só elas disputam
        // ordem entre si. As de saída nascem resolvidas, para nunca segurar a
        // vez de uma resposta do associado enquanto ele espera.
        $entrada = $parser->extractDirection($payload) === 'IN';

        $messageRow = UberAccessRequestMessage::create([
            'poli_message_id' => $messageId,
            'poli_sequence' => $parser->extractSequence($payload),
            'contact_uuid' => $parser->extractContactUuid($payload),
            'raw_payload' => $payload,
            'processed_at' => $entrada ? null : now(),
        ]);

        if ($entrada) {
            // O atraso é o que dá tempo de uma mensagem atrasada chegar: só dá
            // para ceder a vez a uma irmã que já esteja gravada.
            ProcessUberAccessRequestMessage::dispatch($messageRow->id)
                ->delay(now()->addSeconds((int) config('poli.inbound.ordering_delay_seconds', 20)));
        }

        // O fecho do atendimento é anunciado pelas mensagens do bot, que não
        // entram no fluxo e portanto não têm job. Como ele não depende de
        // ordem nenhuma, sai daqui mesmo — igual ao índice do menu.
        $this->dispatchAttendanceClosure($payload, $parser);

        return response()->json(['status' => 'accepted'], 200);
    }

    /**
     * Agenda o encerramento da coleta quando a Poli fecha o atendimento.
     *
     * O atraso e o motivo dele estão em UberAccessRequestFlow::CLOSURE_GRACE_SECONDS.
     */
    private function dispatchAttendanceClosure(array $payload, PoliMessageParser $parser): void
    {
        $atendimento = $parser->extractFinishedAttendanceUuid($payload);

        if ($atendimento === null) {
            return;
        }

        CloseUberCaptureSession::dispatch($atendimento)
            ->delay(now()->addSeconds(UberAccessRequestFlow::CLOSURE_GRACE_SECONDS));
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
