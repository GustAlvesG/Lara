<?php

namespace App\Jobs;

use App\Services\Poli\PoliMessageService;
use App\Services\PoliBot\UberArrivalHandover;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Manda UMA mensagem de texto pela Poli, fora do ciclo da requisição.
 *
 * Está numa fila por causa do teto de 60 req/min da conta, que é global à
 * aplicação: quem envia precisa poder esperar, e quem libera o acesso na
 * portaria não pode. O porteiro clica, o acesso é registrado na hora, e o
 * aviso ao associado sai logo atrás.
 *
 * O texto viaja pronto, montado no instante do acesso, em vez de ser
 * remontado aqui a partir do id do pedido. É de propósito: a mensagem
 * descreve um fato daquele momento ("o carro chegou"), e não o estado que o
 * registro tiver quando a fila chegar nele.
 */
class SendPoliTextMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Tentativas contando as adiadas por rate limit. O 429 não é erro nosso —
     * é a conta ocupada —, então cabe insistir mais do que se insistiria com
     * uma falha de verdade.
     */
    public int $tries = 5;

    /**
     * Espera padrão entre tentativas quando a Poli não diz quanto esperar.
     * Crescente: 1min, 2min, 5min.
     *
     * @var int[]
     */
    public array $backoff = [60, 120, 300];

    public function __construct(
        public readonly string $phone,
        public readonly string $text,
        public readonly ?string $contactUuid = null,
        public readonly ?string $channelUuid = null,
        public readonly ?int $uberAccessRequestId = null,
        /**
         * Aviso do Uber: depois dele a Lara cuida do atendimento — nada, O
         * Lara ou close (UberArrivalHandover). O nome é de antes, quando era
         * sempre close.
         */
        public readonly bool $closeAfter = false,
    ) {}

    /**
     * Um único balde para toda a aplicação (ver config/poli.php). Vale para
     * este Job e para qualquer outro que venha a enviar pela mesma conta —
     * é o que mantém a soma dos fluxos dentro dos 60/min reais.
     */
    public function middleware(): array
    {
        return [new RateLimited((string) config('poli.rate_limit.name', 'poli-outbound'))];
    }

    public function handle(PoliMessageService $poli, UberArrivalHandover $handover): void
    {
        // O `?? false` cobre o job enfileirado pelo código anterior a esta
        // propriedade — desserializado, ele chega sem ela.
        $cuidar = ($this->closeAfter ?? false) && $poli->enabled();

        // Com o contato conhecido, decide ANTES do aviso: se a conversa vai
        // para O Lara, ela vai antes, para a resposta do sócio chegar à Lara.
        $plano = $cuidar && filled($this->contactUuid) ? $handover->prepare($this->contactUuid) : null;

        $result = $poli->sendTextByPhone(
            $this->phone,
            $this->comRodape($plano),
            $this->channelUuid,
            $this->contactUuid,
        );

        if ($result->success) {
            if ($cuidar) {
                $this->depoisDoAviso($poli, $handover, $plano, $result->contactUuid);
            }

            return;
        }

        if ($result->retryable && $this->attempts() < $this->tries) {
            // release() devolve o job à fila SEM contar como falha. O
            // Retry-After da Poli manda quando ela o informa; senão, cai no
            // degrau de backoff correspondente à tentativa atual.
            $this->release($result->retryAfter ?? $this->currentBackoff());

            Log::info('Poli: envio adiado', [
                'uber_access_request_id' => $this->uberAccessRequestId,
                'http_status' => $result->httpStatus,
                'tentativa' => $this->attempts(),
                'erro' => $result->error,
            ]);

            return;
        }

        // Chegou aqui: ou o erro não se resolve repetindo (telefone inválido,
        // payload recusado), ou as tentativas acabaram. Em nenhum dos dois o
        // job deve estourar: o acesso do motorista já foi liberado e o
        // registro está no banco — o que se perdeu foi só o aviso.
        Log::warning('Poli: mensagem não entregue', [
            'uber_access_request_id' => $this->uberAccessRequestId,
            'http_status' => $result->httpStatus,
            'tentativas' => $this->attempts(),
            'erro' => $result->error,
        ]);
    }

    /**
     * Depois do aviso aceito, o destino do atendimento (ver
     * UberArrivalHandover). Falha aqui só vira log: o aviso já saiu, e
     * reagendar o job o mandaria de novo.
     *
     * Aviso enviado só pelo telefone não tinha contato para decidir antes: a
     * decisão sai agora, com o contato que veio na resposta.
     */
    private function depoisDoAviso(
        PoliMessageService $poli,
        UberArrivalHandover $handover,
        ?string $plano,
        ?string $contatoDaResposta,
    ): void {
        $contato = filled($this->contactUuid) ? $this->contactUuid : $contatoDaResposta;

        if (blank($contato)) {
            Log::warning('Poli: aviso enviado, mas sem contato para cuidar da conversa', [
                'uber_access_request_id' => $this->uberAccessRequestId,
            ]);

            return;
        }

        $plano ??= $handover->prepare($contato);

        if ($plano === UberArrivalHandover::ENCERRAR) {
            $poli->closeChat($contato);
        }
    }

    /**
     * O texto viaja sem o rodapé, que depende do destino do atendimento. O
     * job enfileirado antes desta versão já traz o rodapé no texto — aí ele
     * não é repetido.
     */
    private function comRodape(?string $plano): string
    {
        $rodape = UberArrivalHandover::footer($plano);

        if ($rodape === null || str_ends_with($this->text, $rodape)
            || str_ends_with($this->text, (string) config('poli.messages.uber_arrival.rodape'))) {
            return $this->text;
        }

        return $this->text . "\n\n" . $rodape;
    }

    private function currentBackoff(): int
    {
        $index = max(0, $this->attempts() - 1);

        return $this->backoff[$index] ?? end($this->backoff);
    }
}
