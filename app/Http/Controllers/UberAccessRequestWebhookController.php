<?php

namespace App\Http\Controllers;

use App\Jobs\CloseUberCaptureSession;
use App\Jobs\ProcessPoliBotRedirect;
use App\Jobs\ProcessUberAccessRequestMessage;
use App\Models\PoliListMessage;
use App\Models\UberAccessRequestMessage;
use App\Services\Poli\PoliMessageParser;
use App\Services\PoliBot\BotEngine;
use App\Services\UberAccessRequestFlow;
use Illuminate\Contracts\Cache\LockTimeoutException;
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

        // Os headers do reenvio viajam dentro do payload até o job: um evento
        // reenviado pode trazer o atendimento como estava na primeira entrega.
        $meta = array_filter([
            'attempt' => $request->header('X-Webhook-Attempt'),
            'delivery_id' => $request->header('X-Webhook-Delivery-Id'),
        ], 'filled');
        if ($meta !== []) {
            $payload[PoliMessageParser::WEBHOOK_META] = $meta;
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
        $this->observe($payload, $parser, $bot);

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
                ->delay(now()->addSeconds($this->esperaDeEntrega($payload, $parser, $bot)));
        }

        // Transferência para O Lara: a Lara abre o fluxo sem esperar a próxima
        // mensagem do contato. Com o mesmo atraso das mensagens da conversa
        // dela, para o toque no menu que motivou a transferência chegar antes.
        if ($bot->mode() !== BotEngine::MODE_OFF && $parser->isRedirect($payload)
            && $bot->isLaraAttendant($parser->extractAttendantUuid($payload))) {
            ProcessPoliBotRedirect::dispatch($messageRow->id)
                ->delay(now()->addSeconds((int) config('poli.bot.inbound_max_lag_seconds', 6)));
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
    private function esperaDeEntrega(array $payload, PoliMessageParser $parser, BotEngine $bot): int
    {
        // Na conversa do O Lara, que a Lara sempre responde, a espera vira
        // tempo de resposta na cara do associado: 20s por mensagem é conversa
        // travada. O teto do bot é menor e troca um pouco de proteção contra
        // inversão (medidas: 1 a 12s, 8 em 2081) por uma conversa que anda. As
        // conversas do bot da Poli, onde trabalha a escuta do Uber, seguem
        // com o teto dela.
        $conversaDaLara = $bot->mode() !== BotEngine::MODE_OFF
            && $bot->isLaraAttendant($parser->extractAttendantUuid($payload));

        $teto = $conversaDaLara
            ? (int) config('poli.bot.inbound_max_lag_seconds', 6)
            : (int) config('poli.inbound.max_delivery_lag_seconds', 20);

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
     * O estado do bot, com a trava do contato: a sessão pode estar sendo
     * gravada pelo job da mesma conversa. Trava ocupada por tempo demais não
     * segura o webhook — observa sem ela (é só estado, e a Poli reenviaria o
     * evento inteiro se o webhook demorasse).
     */
    private function observe(array $payload, PoliMessageParser $parser, BotEngine $bot): void
    {
        $contato = $parser->extractContactUuid($payload);

        if ($contato === null || $bot->mode() === BotEngine::MODE_OFF) {
            $bot->observe($payload);

            return;
        }

        try {
            $bot->exclusive($contato, fn () => $bot->observe($payload), waitSeconds: 5);
        } catch (LockTimeoutException) {
            Log::warning('PoliBot: evento observado sem a trava do contato', ['contact_uuid' => $contato]);
            $bot->observe($payload);
        }
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
