<?php

namespace App\Http\Controllers\Ticket;

use App\Exceptions\TicketException;
use App\Http\Controllers\Controller;
use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketEvent;
use App\Services\Ticket\TicketCheckinService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Check-in na entrada do evento (permissão `ingressos.checkin`). Escolhe-se
 * o evento; o campo aceita o código do ingresso (leitor de QR ou digitado) ou
 * o CPF do titular. Enter registra a entrada; "Só consultar" não grava.
 * Fora da janela de entrada, o Lara pede confirmação. No evento de vários
 * dias, o passaporte entra uma vez por dia e a diária só no dia dela.
 */
class TicketCheckinController extends Controller
{
    public function __construct(private TicketCheckinService $checkin)
    {
    }

    public function index(Request $request): View
    {
        $events = TicketEvent::checkinWindow()->orderBy('starts_at')->get();
        $event = $request->filled('event')
            ? TicketEvent::whereIn('status', [TicketEvent::STATUS_PUBLISHED, TicketEvent::STATUS_FINISHED])->find($request->integer('event'))
            : $events->first();

        return view('tickets.checkin', [
            'events' => $events,
            'event' => $event,
            // No evento de vários dias, o contador é do dia (aberto agora, ou o próximo).
            'counts' => $event ? $this->checkin->counts($event, $this->checkin->dayForCounts($event)) : null,
            'upcoming' => TicketEvent::where('status', TicketEvent::STATUS_PUBLISHED)->where('ends_at', '>=', now())->orderBy('starts_at')->limit(20)->get(),
        ]);
    }

    public function store(Request $request, TicketEvent $event): RedirectResponse
    {
        $data = $request->validate(['target' => 'required|string|max:40']);
        $onlyCheck = $request->boolean('only_check');
        $back = redirect()->route('tickets.checkin.index', ['event' => $event->id]);

        $tickets = $this->checkin->find($data['target'], TicketCheckinService::looksLikeCode($data['target']) ? null : $event);

        if ($tickets->isEmpty()) {
            return $back->with('checkin', ['allowed' => false, 'title' => 'Ingresso não encontrado', 'message' => 'Nenhum ingresso pago com este código ou CPF neste evento.', 'target' => $data['target']]);
        }

        $ticket = $tickets->first();
        if ($ticket->ticket_event_id !== $event->id) {
            return $back->with('checkin', $this->result($ticket, false, 'Este ingresso é de outro evento: ' . $ticket->event->title . '.'));
        }

        if ($onlyCheck) {
            $state = $this->checkin->evaluate($ticket);

            return $back->with('checkin', $this->result($ticket, $state['allowed'], $state['message'], consult: true));
        }

        try {
            $ticket = $this->checkin->checkIn($ticket, $request->user(), Ticket::CHECKIN_LARA, $request->boolean('force'));
        } catch (TicketException $e) {
            return $back->with('checkin', $this->result($ticket->fresh(['event', 'type', 'lot', 'checker']), false, $e->getMessage()));
        }

        return $back->with('checkin', $this->result($ticket, true, 'Entrada registrada às ' . $ticket->checked_in_at->format('H:i') . '.'));
    }

    private function result(Ticket $ticket, bool $allowed, string $message, bool $consult = false): array
    {
        return [
            'allowed' => $allowed,
            'consult' => $consult,
            'title' => $ticket->holder_name,
            'message' => $message,
            'ticket' => [
                'code' => $ticket->code,
                'cpf' => \App\Support\Cpf::mask($ticket->holder_cpf),
                'type' => $ticket->type?->fullName(),
                'lot' => $ticket->lot?->name,
                'status' => Ticket::STATUS_LABELS[$ticket->status] ?? $ticket->status,
                'order_id' => $ticket->ticket_order_id,
                'checked_in_by' => $ticket->checker?->name,
            ],
        ];
    }
}
