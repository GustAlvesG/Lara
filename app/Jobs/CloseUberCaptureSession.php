<?php

namespace App\Jobs;

use App\Models\UberAccessRequestMessage;
use App\Services\UberAccessRequestFlow;
use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Encerra a coleta de um atendimento que a Poli fechou.
 *
 * Vive numa fila com atraso, e não no caminho do webhook, por causa da corrida
 * descrita em UberAccessRequestFlow::CLOSURE_GRACE_SECONDS: a despedida do bot
 * sai no mesmo segundo em que o print chega, e fechar na hora mataria o pedido
 * no instante em que ele ficou pronto.
 *
 * As três mensagens de despedida trazem o mesmo `closed_reason`, então este
 * job é despachado mais de uma vez para o mesmo atendimento — o que não é
 * problema: a segunda passagem não encontra mais nada em coleta.
 */
class CloseUberCaptureSession implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $attendanceUuid) {}

    /**
     * Teto de vida da espera. Estourado o prazo o fecho desiste e a coleta
     * fica por conta do timeout de inatividade — é o que impede uma mensagem
     * presa na fila de segurar o fecho para sempre.
     */
    public function retryUntil(): DateTimeInterface
    {
        return now()->addMinutes((int) config('poli.inbound.retry_until_minutes', 5));
    }

    public function handle(UberAccessRequestFlow $flow): void
    {
        // A carência acima é um relógio; esta é a checagem de verdade. As
        // respostas do associado entram na fila com atraso, e fechar com fila
        // por drenar apaga justamente as últimas etapas do pedido.
        $janela = (int) config('poli.inbound.max_delivery_lag_seconds', 20)
            + (int) config('poli.inbound.ordering_wait_seconds', 45);

        if (UberAccessRequestMessage::hasPendingForAttendance($this->attendanceUuid, $janela)) {
            if ($this->job) {
                $this->release((int) config('poli.inbound.defer_seconds', 3));

                return;
            }
        }

        $flow->closeCaptureForAttendance($this->attendanceUuid);
    }
}
