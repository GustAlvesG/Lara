<?php

namespace App\Http\Controllers;

use App\Jobs\CloseUberCaptureSession;
use App\Jobs\ProcessUberAccessRequestMessage;
use App\Models\PoliListMessage;
use App\Models\UberAccessRequestMessage;
use App\Services\Poli\PoliMessageParser;
use App\Services\PoliBot\BotEngine;
use App\Services\UberAccessRequestFlow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UberAccessRequestWebhookController extends Controller
{
    public function handle(Request $request, PoliMessageParser $parser, BotEngine $bot): JsonResponse
    {
        $payload = $request->all();

        // Evento de outra conta da Poli: não é conosco. 200 para ela não
        // insistir; nada é gravado.
        $conta = config('poli.account_uuid');
        if (filled($conta) && filled($payload['account_uuid'] ?? null) && $payload['account_uuid'] !== $conta) {
            return response()->json(['status' => 'ignored'], 200);
        }

        // ACK no formato da documentação vem sem `value`. Não é mensagem, mas
        // também não é payload quebrado: um 422 aqui faria a Poli reenviar
        // até desistir do webhook.
        if (($payload['event'] ?? null) === 'ack' && !isset($payload['value'])) {
            $bot->observe($payload);

            return response()->json(['status' => 'accepted'], 200);
        }

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

        // Estado do bot (atendente assumiu, atendimento encerrado, ACK das
        // mensagens dele). Também antes da duplicidade: o reenvio por mudança
        // de `ack` é justamente o que atualiza o ACK. Não lança, e sem modo
        // ligado não faz nada.
        $bot->observe($payload);

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
            // A espera é o que dá tempo de uma mensagem atrasada chegar: só dá
            // para ceder a vez a uma irmã que já esteja gravada.
            ProcessUberAccessRequestMessage::dispatch($messageRow->id)
                ->delay(now()->addSeconds($this->esperaDeEntrega($payload, $parser)));
        }

        // O fecho do atendimento é anunciado pelas mensagens do bot, que não
        // entram no fluxo e portanto não têm job. Como ele não depende de
        // ordem nenhuma, sai daqui mesmo — igual ao índice do menu.
        $this->dispatchAttendanceClosure($payload, $parser);

        return response()->json(['status' => 'accepted'], 200);
    }

    /**
     * Quantos segundos esta mensagem ainda precisa esperar para que nenhuma
     * irmã mais antiga possa estar a caminho.
     *
     * A conta é feita a partir da hora em que a POLI CRIOU a mensagem, e não
     * da hora em que ela chegou aqui. É a diferença entre as duas abordagens
     * que importa:
     *
     *   - ancorado na chegada, todo mundo espera o mesmo tanto, e o atraso do
     *     webhook SOMA com a espera. Uma mensagem entregue 12s atrasada só era
     *     processada 12s + espera depois de ter sido escrita;
     *   - ancorado na criação, a espera é o que FALTA para o teto. Quem chegou
     *     atrasado já gastou o tempo no caminho e segue direto; quem chegou na
     *     hora aguarda o teto inteiro.
     *
     * O efeito é que toda mensagem entra no fluxo a `max_delivery_lag` da sua
     * criação — um prazo fixo, que não cresce com o atraso da entrega. E como
     * a régua é a criação da mensagem, o ritmo da conversa não entra na conta:
     * o associado pode levar o tempo que quiser entre uma resposta e outra.
     */
    private function esperaDeEntrega(array $payload, PoliMessageParser $parser): int
    {
        $teto = (int) config('poli.inbound.max_delivery_lag_seconds', 20);

        $criadaEm = $parser->extractCreatedAt($payload);

        // Sem hora de criação não há como descontar nada: espera o teto, que é
        // o comportamento antigo e o seguro.
        if ($criadaEm === null) {
            return $teto;
        }

        $gastoNoCaminho = now()->getTimestamp() - $criadaEm->getTimestamp();

        // O relógio da Poli e o nosso podem discordar; um adiantamento traria
        // gasto negativo e uma espera MAIOR que o teto. Daí os dois limites.
        return (int) max(0, min($teto, $teto - $gastoNoCaminho));
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
