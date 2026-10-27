<?php

namespace App\Console\Commands;

use App\Services\Ticket\TicketOrderService;
use Illuminate\Console\Command;

/**
 * Passa a `expired` os pedidos de ingresso pendentes cujo prazo
 * (`tickets.hold_minutes`) venceu. Agendado a cada minuto.
 *
 * O estoque já volta à venda no fim do prazo, mesmo sem este comando
 * (`Ticket::occupying` não conta pendente vencido); ele só põe a situação
 * gravada em dia para as telas e o site.
 */
class ExpireTicketOrders extends Command
{
    protected $signature = 'ingressos:expirar-pedidos';

    protected $description = 'Expira os pedidos de ingresso pendentes cujo prazo de pagamento venceu';

    public function handle(TicketOrderService $orders): int
    {
        $count = $orders->expireOverdue();

        if ($count > 0) {
            $this->info("{$count} pedido(s) de ingresso expirado(s).");
        }

        return self::SUCCESS;
    }
}
