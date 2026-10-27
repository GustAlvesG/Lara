<?php

namespace App\Http\Controllers\Ticket;

use App\Http\Controllers\Controller;
use App\Models\Ticket\TicketEvent;
use App\Services\Ticket\TicketFinanceService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Ingressos › Financeiro (permissão `ingressos.financeiro`): lista, evento e CSV. */
class TicketFinanceController extends Controller
{
    public function __construct(private TicketFinanceService $finance)
    {
    }

    public function index(Request $request): View
    {
        $year = $request->integer('ano') ?: (int) now()->year;

        $events = TicketEvent::whereIn('status', [TicketEvent::STATUS_PUBLISHED, TicketEvent::STATUS_FINISHED, TicketEvent::STATUS_CANCELLED])
            ->whereYear('starts_at', $year)
            ->orderByDesc('starts_at')
            ->get();

        return view('tickets.finance.index', [
            'year' => $year,
            'rows' => $events->map(fn (TicketEvent $e) => ['event' => $e] + $this->finance->totals($e)),
        ]);
    }

    public function show(TicketEvent $event): View
    {
        return view('tickets.finance.show', [
            'event' => $event,
            'totals' => $this->finance->totals($event),
            'byLot' => $this->finance->byLot($event),
            'byMethod' => $this->finance->byMethod($event),
            'byCoupon' => $this->finance->byCoupon($event),
        ]);
    }

    public function csv(TicketEvent $event): StreamedResponse
    {
        $rows = $this->finance->csvRows($event);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            // BOM: o Excel abre com acento certo.
            fwrite($out, "\xEF\xBB\xBF");
            foreach ($rows as $row) {
                fputcsv($out, $row, ';');
            }
            fclose($out);
        }, "ingressos-evento-{$event->id}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
