<?php

namespace App\Jobs;

use App\Services\UberAccessRequestFlow;
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

    public function handle(UberAccessRequestFlow $flow): void
    {
        $flow->closeCaptureForAttendance($this->attendanceUuid);
    }
}
