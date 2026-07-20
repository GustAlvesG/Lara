<?php

namespace App\Console\Commands;

use App\Models\UberAccessRequest;
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
    protected $description = 'Marca como expirado os pedidos de Uber que estavam aguardando o acesso do motorista e cuja validade (expires_at) venceu sem acesso.';

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
            $this->info("{$expired} pedido(s) de Uber expirado(s).");
        }
    }
}
