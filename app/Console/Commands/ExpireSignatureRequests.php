<?php

namespace App\Console\Commands;

use App\Services\Signature\SignatureRequestService;
use Illuminate\Console\Command;

/**
 * Fecha o que ficou aberto no módulo de assinatura.
 *
 * Três situações, todas do balcão de verdade: o QR que o atendente gerou e
 * ninguém leu; a sessão do tablet que ficou na tela porque a pessoa desistiu e
 * foi embora; e o documento congelado que passou o dia sem ninguém assinar.
 *
 * Roda a cada minuto, como `app:expire-pending-schedules` — o prazo do QR é de
 * minutos, e um varredor de hora em hora deixaria a tela do atendente
 * mostrando contagem regressiva de um código que já não vale.
 *
 * Nada aqui é apagado: tudo vira transição de estado com evento de auditoria.
 */
class ExpireSignatureRequests extends Command
{
    protected $signature = 'signature:expire';

    protected $description = 'Expira QR Codes não lidos, sessões de tablet paradas e documentos não assinados';

    public function handle(SignatureRequestService $requests): int
    {
        $resultado = $requests->expireDue();

        if ($resultado['requests'] === 0 && $resultado['documents'] === 0) {
            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Assinatura: %d liberação(ões) e %d documento(s) expirados.',
            $resultado['requests'],
            $resultado['documents'],
        ));

        return self::SUCCESS;
    }
}
