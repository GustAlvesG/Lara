<?php

namespace App\Jobs;

use App\Models\UberAccessRequestMessage;
use App\Services\Poli\ParsedPoliMessage;
use App\Services\Poli\PoliMessageParser;
use App\Services\PoliBot\BotEngine;
use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * O atendimento foi transferido para O Lara: a Lara abre o fluxo.
 *
 * O gatilho é a última mensagem do contato ANTES da transferência — o toque
 * no menu do bot da Poli. Ela é lida direto do webhook gravado, sem esperar
 * o job dela: esse toque é de uma conversa do bot da Poli e entra na fila com
 * a espera longa da escuta do Uber (20 s). Esperar por ele fazia a Lara
 * responder uns 20 s depois do toque. Quando o job do toque rodar, a conversa
 * já é do O Lara e ele só fica no histórico (BotEngine::process).
 *
 * O atraso deste job (poli.bot.inbound_max_lag_seconds) é o que dá tempo de
 * o webhook do toque chegar, se ele vier atrasado.
 */
class ProcessPoliBotRedirect implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Até quanto tempo antes da transferência a mensagem do contato vale como gatilho. */
    private const TRIGGER_MINUTES = 30;

    public function __construct(public int $uberAccessRequestMessageId) {}

    public function retryUntil(): DateTimeInterface
    {
        return now()->addMinutes((int) config('poli.inbound.retry_until_minutes', 5));
    }

    public function handle(PoliMessageParser $parser, BotEngine $bot): void
    {
        $linha = UberAccessRequestMessage::find($this->uberAccessRequestMessageId);
        $redirect = $linha ? $parser->parseRedirect($linha->raw_payload) : null;

        if ($redirect === null) {
            return;
        }

        $gatilho = $this->lastInbound($linha, $parser);

        try {
            $bot->exclusive($redirect->contactUuid, fn () => $bot->handleRedirect($redirect, $gatilho));
        } catch (LockTimeoutException) {
            $this->release((int) config('poli.inbound.defer_seconds', 3));
        }
    }

    /**
     * A última mensagem de texto do contato recebida antes da transferência,
     * pela ordem da Poli quando há sequência, senão pela de chegada.
     */
    private function lastInbound(UberAccessRequestMessage $redirect, PoliMessageParser $parser): ?ParsedPoliMessage
    {
        $anteriores = UberAccessRequestMessage::where('contact_uuid', $redirect->contact_uuid)
            ->where('id', '<', $redirect->id)
            ->where('created_at', '>=', now()->subMinutes(self::TRIGGER_MINUTES))
            ->orderByDesc('poli_sequence')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        foreach ($anteriores as $linha) {
            if ($parser->isRelevantEvent($linha->raw_payload)) {
                $mensagem = $parser->parse($linha->raw_payload);

                return $mensagem?->type === ParsedPoliMessage::TYPE_TEXT ? $mensagem : null;
            }
        }

        return null;
    }
}
