<?php

namespace App\Services\Ticket;

use App\Exceptions\TicketException;
use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketOrder;
use App\Models\Ticket\TicketOrderLog;
use App\Models\User;
use App\Services\Signature\SignatureQrCode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Ingresso físico na impressora térmica de 80 mm (docs/funcionalidades/ingressos.md).
 *
 * A impressão é pelo navegador: o Lara monta uma página no tamanho do cupom
 * (um ingresso por página, para o corte) e o driver do Windows imprime. Cada
 * impressão conta a via do ingresso (`print_count`): da segunda em diante sai
 * "2ª via", e toda impressão vai para o histórico do pedido.
 *
 * Só ingresso válido de pedido pago. O QR leva o mesmo código `ING-` do PDF:
 * o check-in não muda.
 */
class TicketPrintService
{
    public function __construct(private SignatureQrCode $qr)
    {
    }

    /**
     * Conta a via e registra quem imprimiu. `$ticketIds` vazio = todos os
     * válidos do pedido.
     *
     * @param  list<int>  $ticketIds
     * @return Collection<int, array{ticket: Ticket, copy: int, qr: string}>
     */
    public function prepare(TicketOrder $order, array $ticketIds, User $user): Collection
    {
        if ($order->status !== TicketOrder::STATUS_PAID) {
            throw new TicketException(TicketException::INVALID_STATUS, 'Só pedido pago imprime ingresso.');
        }

        $tickets = $order->tickets()
            ->with(['type.day', 'lot', 'seat.map'])
            ->where('status', Ticket::STATUS_VALID)
            ->when($ticketIds !== [], fn ($q) => $q->whereIn('id', $ticketIds))
            ->get();

        if ($tickets->isEmpty()) {
            throw new TicketException(TicketException::TICKET_NOT_VALID, 'Não há ingresso válido para imprimir neste pedido.');
        }

        $now = Carbon::now();

        DB::transaction(function () use ($tickets, $order, $user, $now) {
            foreach ($tickets as $ticket) {
                $ticket->print_count = $ticket->print_count + 1;
                $ticket->printed_at = $now;
                $ticket->save();
                TicketOrderLog::record($order, TicketOrderLog::PRINTED, $user, ['holder' => $ticket->holder_name, 'copy' => $ticket->print_count], $ticket);
            }
        });

        return $tickets->map(fn (Ticket $t) => ['ticket' => $t, 'copy' => $t->print_count, 'qr' => $this->qr->dataUri($t->code, 240)])->values();
    }
}
