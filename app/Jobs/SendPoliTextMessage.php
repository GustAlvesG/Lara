<?php

namespace App\Jobs;

use App\Services\Poli\PoliMessageService;
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

    public function handle(PoliMessageService $poli): void
    {
        $result = $poli->sendTextByPhone(
            $this->phone,
            $this->text,
            $this->channelUuid,
            $this->contactUuid,
        );

        if ($result->success) {
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

    private function currentBackoff(): int
    {
        $index = max(0, $this->attempts() - 1);

        return $this->backoff[$index] ?? end($this->backoff);
    }
}
