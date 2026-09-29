<?php

namespace App\Jobs;

use App\Models\UberAccessRequestMessage;
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
 * Fila com atraso, e não no webhook, porque o gatilho é a última mensagem do
 * contato ANTES da transferência — o toque no menu do bot da Poli —, e ela
 * chega quase junto e passa pela fila de ordem com atraso. Enquanto houver
 * mensagem do contato recebida antes desta e ainda não processada, cede a vez.
 */
class ProcessPoliBotRedirect implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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

        $janela = (int) config('poli.inbound.ordering_wait_seconds', 45);
        $irmaPendente = UberAccessRequestMessage::where('contact_uuid', $redirect->contactUuid)
            ->where('id', '<', $linha->id)
            ->whereNull('processed_at')
            ->where('created_at', '>=', now()->subSeconds($janela))
            ->exists();

        if ($irmaPendente && $this->job) {
            $this->release((int) config('poli.inbound.defer_seconds', 3));

            return;
        }

        try {
            $bot->exclusive($redirect->contactUuid, fn () => $bot->handleRedirect($redirect));
        } catch (LockTimeoutException) {
            $this->job?->release((int) config('poli.inbound.defer_seconds', 3));
        }
    }
}
