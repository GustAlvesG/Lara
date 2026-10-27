<?php

namespace App\Services\Ticket;

use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketOrder;
use App\Services\Signature\SignatureQrCode;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

/**
 * PDF dos ingressos pagos de um pedido: uma página por ingresso, com o QR do
 * código (SVG em data URI, que o dompdf desenha sem a extensão GD).
 */
class TicketPdf
{
    public function __construct(private SignatureQrCode $qr)
    {
    }

    public function download(TicketOrder $order): Response
    {
        $order->loadMissing('event', 'tickets.type', 'tickets.lot');

        $tickets = $order->tickets
            ->filter(fn (Ticket $t) => in_array($t->status, Ticket::SOLD, true))
            ->map(fn (Ticket $t) => ['ticket' => $t, 'qr' => $this->qr->dataUri($t->code, 220)])
            ->values();

        return Pdf::loadView('tickets.pdf', ['order' => $order, 'tickets' => $tickets])
            ->setPaper('a4')
            ->download("ingressos-pedido-{$order->id}.pdf");
    }
}
