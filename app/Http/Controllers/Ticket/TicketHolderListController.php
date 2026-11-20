<?php

namespace App\Http\Controllers\Ticket;

use App\Http\Controllers\Controller;
use App\Models\Ticket\TicketEvent;
use App\Services\Ticket\TicketHolderList;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Ingressos › Listagem (permissão `ingressos.listagem`): nome, CPF e ingresso
 * dos compradores do evento, por dia e tipo, em PDF (para imprimir) ou CSV.
 */
class TicketHolderListController extends Controller
{
    public function __construct(private TicketHolderList $list)
    {
    }

    public function pdf(Request $request, TicketEvent $event): Response
    {
        return Pdf::loadView('tickets.holders.pdf', [
            'event' => $event,
            'groups' => $this->list->groups($event),
            'by' => $request->user(),
        ])->setPaper('a4')->download("listagem-ingressos-evento-{$event->id}.pdf");
    }

    public function csv(TicketEvent $event): StreamedResponse
    {
        $rows = $this->list->csvRows($event);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            // BOM: o Excel abre com acento certo.
            fwrite($out, "\xEF\xBB\xBF");
            foreach ($rows as $row) {
                fputcsv($out, $row, ';');
            }
            fclose($out);
        }, "listagem-ingressos-evento-{$event->id}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
