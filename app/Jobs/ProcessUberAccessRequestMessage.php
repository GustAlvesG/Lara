<?php

namespace App\Jobs;

use App\Exceptions\PoliListMessageNotIndexedException;
use App\Models\UberAccessRequestMessage;
use App\Services\Poli\PoliMessageParser;
use App\Services\PoliBot\BotEngine;
use App\Services\UberAccessRequestFlow;
use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessUberAccessRequestMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $uberAccessRequestMessageId) {}

    /**
     * O job espera por prazo, não por número de tentativas.
     *
     * São duas esperas diferentes — ceder a vez para uma mensagem mais antiga
     * e aguardar o menu ser indexado — e contar tentativas misturaria as duas:
     * quem cedesse a vez três vezes chegaria no menu sem tentativa nenhuma
     * sobrando. O prazo é o teto de ambas, e garante que nenhuma espera vire
     * laço infinito.
     */
    public function retryUntil(): DateTimeInterface
    {
        return now()->addMinutes((int) config('poli.inbound.retry_until_minutes', 5));
    }

    public function handle(PoliMessageParser $parser, UberAccessRequestFlow $flow, BotEngine $bot): void
    {
        $messageRow = UberAccessRequestMessage::find($this->uberAccessRequestMessageId);

        if (!$messageRow || $messageRow->processed_at !== null) {
            return;
        }

        // Ordem antes de tudo: a Poli entrega fora de ordem, e o fluxo é uma
        // máquina de estados — processar na frente de uma mensagem mais antiga
        // põe a resposta no campo errado e desloca o pedido inteiro.
        if ($messageRow->hasPendingPredecessor((int) config('poli.inbound.ordering_wait_seconds', 45))) {
            if ($this->job) {
                $this->release((int) config('poli.inbound.defer_seconds', 3));
            }

            // Sem fila por trás (execução direta), não há para onde adiar — e
            // processar fora de ordem é justamente o que não se quer.
            return;
        }

        $payload = $messageRow->raw_payload;

        // O fecho do atendimento não passa por aqui: é anunciado pelas
        // mensagens do bot, que não têm job, e quem o agenda é o controller.

        if (!$parser->isRelevantEvent($payload)) {
            // Áudio, documento, figurinha: o fluxo do Uber não lê, mas o bot
            // responde pedindo texto.
            if ($parser->isUnsupportedInbound($payload) && ($unsupported = $parser->parseUnsupported($payload))) {
                if (!$this->exclusivo($bot, $unsupported->contactUuid, fn () => $bot->handleInbound($unsupported))) {
                    return;
                }
            }

            $messageRow->markProcessed();

            return;
        }

        try {
            $parsed = $parser->parse($payload);

            if (!$parsed) {
                $messageRow->markProcessed();

                return;
            }

            $uberAccessRequest = null;

            $feito = $this->exclusivo($bot, $parsed->contactUuid, function () use ($bot, $flow, $parsed, &$uberAccessRequest) {
                // A escuta acompanha as perguntas do bot da POLI, e só na fase
                // dele (atendimento sem atendente). Na conversa do O Lara quem
                // cria o pedido é o fluxo da própria Lara.
                $uberAccessRequest = $bot->listensToPoliUberFlow($parsed) ? $flow->handle($parsed) : null;

                // Depois da escuta, e só quando ela não pediu para esperar o
                // menu: assim o bot vê cada mensagem uma vez só. Não lança.
                $bot->handleInbound($parsed);
            });

            if (!$feito) {
                return;
            }

            $messageRow->markProcessed($uberAccessRequest?->id);
        } catch (PoliListMessageNotIndexedException $e) {
            // Ainda dá tempo de o menu chegar: devolve para a fila. O prazo
            // conta da CHEGADA da mensagem, e não das tentativas, que também
            // são gastas cedendo a vez na fila de ordem.
            $graca = (int) config('poli.inbound.menu_index_grace_seconds', 45);

            if ($this->job && $messageRow->created_at?->diffInSeconds(now()) < $graca) {
                $this->release(10);

                return;
            }

            Log::warning('ProcessUberAccessRequestMessage: gatilho recusado, menu nunca indexado', [
                'uber_access_request_message_id' => $this->uberAccessRequestMessageId,
                'poli_message_uuid' => $e->poliMessageUuid,
            ]);

            $messageRow->markProcessed();
        } catch (\Throwable $e) {
            Log::error('ProcessUberAccessRequestMessage: falha ao processar mensagem', [
                'uber_access_request_message_id' => $this->uberAccessRequestMessageId,
                'error' => $e->getMessage(),
            ]);

            // Resolvida mesmo com erro: segurá-la pendente travaria todas as
            // mensagens seguintes daquele contato até a janela expirar.
            $messageRow->markProcessed();
        }
    }

    /**
     * Roda com a trava do contato (a transferência para O Lara e esta
     * mensagem podem estar sendo processadas ao mesmo tempo). Trava ocupada
     * demais: devolve para a fila ANTES de a escuta ou o bot tocarem em
     * qualquer coisa, para a volta não processar nada duas vezes.
     *
     * @return bool false quando devolveu para a fila
     */
    private function exclusivo(BotEngine $bot, ?string $contato, callable $callback): bool
    {
        if ($contato === null) {
            $callback();

            return true;
        }

        try {
            $bot->exclusive($contato, $callback);

            return true;
        } catch (LockTimeoutException) {
            if ($this->job) {
                $this->release((int) config('poli.inbound.defer_seconds', 3));

                return false;
            }

            // Sem fila (execução direta), não há para onde adiar.
            $callback();

            return true;
        }
    }
}
