<?php

namespace App\Console\Commands;

use App\Models\UberAccessRequest;
use App\Services\UberAccessRequestFlow;
use Illuminate\Console\Command;

class ExpireUberAccessRequests extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:expire-uber-access-requests';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expira pedidos de Uber: os que aguardavam o acesso do motorista e cuja validade (expires_at) venceu, e as coletas abandonadas no WhatsApp que passaram do tempo de resposta.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $expired = UberAccessRequest::where('status', UberAccessRequest::STATUS_AGUARDANDO_ACESSO)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update(['status' => UberAccessRequest::STATUS_EXPIRADO]);

        if ($expired > 0) {
            $this->info("{$expired} pedido(s) de Uber expirado(s) por validade vencida.");
        }

        $abandoned = $this->expireAbandonedCaptures();

        if ($abandoned > 0) {
            $this->info("{$abandoned} coleta(s) abandonada(s) cancelada(s) por falta de resposta.");
        }
    }

    /**
     * Cancela as coletas em que o associado parou de responder. O fluxo também
     * detecta isso de forma preguiçosa quando a próxima mensagem chega, mas sem
     * este passo um pedido abandonado ficaria "aguardando ..." indefinidamente
     * para quem consulta a portaria.
     */
    private function expireAbandonedCaptures(): int
    {
        $cutoff = now()->subSeconds(UberAccessRequestFlow::SESSION_TIMEOUT_SECONDS);

        return UberAccessRequest::whereIn('status', UberAccessRequest::CAPTURE_STATUSES)
            ->whereNotNull('last_message_at')
            ->where('last_message_at', '<', $cutoff)
            ->update(['status' => UberAccessRequest::STATUS_EXPIRADO]);
    }
}
