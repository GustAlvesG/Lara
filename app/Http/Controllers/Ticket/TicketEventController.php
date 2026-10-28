<?php

namespace App\Http\Controllers\Ticket;

use App\Http\Controllers\Controller;
use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketEvent;
use App\Models\Ticket\TicketOrder;
use App\Services\Ticket\TicketInventory;
use App\Services\Ticket\TicketSetupService;
use App\Support\ImageSource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Eventos no painel (permissão `ingressos.gerenciar`): lista, cadastro,
 * situação e a tela do evento com os tipos e lotes (TicketTypeController,
 * TicketLotController).
 */
class TicketEventController extends Controller
{
    public function __construct(private TicketSetupService $setup, private TicketInventory $inventory)
    {
    }

    public function index(Request $request): View
    {
        $events = TicketEvent::withCount([
            'tickets as sold_count' => fn ($q) => $q->whereIn('status', Ticket::SOLD),
            'types',
        ])
            ->when($request->filled('q'), fn ($q) => $q->where('title', 'like', '%' . $request->input('q') . '%'))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->orderByDesc('starts_at')
            ->paginate(20)
            ->withQueryString();

        return view('tickets.index', ['events' => $events]);
    }

    public function create(): View
    {
        return view('tickets.form', ['event' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;
        $data['status'] = TicketEvent::STATUS_DRAFT;

        $event = TicketEvent::create($data);

        return redirect()->route('tickets.show', $event)->with('success', 'Evento criado em rascunho. Cadastre os tipos de ingresso e os lotes e publique.');
    }

    public function show(TicketEvent $event): View
    {
        $event->load('types.lots', 'types.day', 'types.map.seats', 'maps.seats');
        $lotIds = $event->types->flatMap->lots->pluck('id');

        return view('tickets.show', [
            'event' => $event,
            'summary' => collect($this->inventory->summary($event))->keyBy(fn ($row) => $row['type']->id),
            'occupied' => $this->inventory->occupiedByLot($lotIds),
            'sold' => Ticket::whereIn('ticket_lot_id', $lotIds)->whereIn('status', Ticket::SOLD)
                ->selectRaw('ticket_lot_id, count(*) as total')->groupBy('ticket_lot_id')->pluck('total', 'ticket_lot_id'),
            'capacityLeft' => $this->inventory->capacityLeft($event),
            'pendingOrders' => $event->orders()->where('status', TicketOrder::STATUS_PENDING_PAYMENT)->count(),
            'transitions' => TicketSetupService::TRANSITIONS[$event->status] ?? [],
        ]);
    }

    public function edit(TicketEvent $event): View
    {
        return view('tickets.form', ['event' => $event]);
    }

    public function update(Request $request, TicketEvent $event): RedirectResponse
    {
        $data = $this->validated($request, $event);

        // Os dias (evento de vários dias) ficam dentro do período do evento.
        $outside = $event->days()->where(fn ($q) => $q->where('starts_at', '<', Carbon::parse($data['starts_at']))
            ->orWhere('ends_at', '>', Carbon::parse($data['ends_at'])))->first();
        if ($outside) {
            throw ValidationException::withMessages(['starts_at' => "O dia {$outside->label()} ficaria fora do período do evento: ajuste ou apague o dia antes."]);
        }

        $event->update($data);

        return redirect()->route('tickets.show', $event)->with('success', 'Evento atualizado.');
    }

    public function status(Request $request, TicketEvent $event): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(TicketEvent::STATUS_LABELS))]]);

        $this->setup->changeStatus($event, $data['status'], $request->boolean('force'));

        return redirect()->route('tickets.show', $event)->with('success', 'Situação: ' . TicketEvent::STATUS_LABELS[$data['status']] . '.');
    }

    private function validated(Request $request, ?TicketEvent $event = null): array
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'rules' => 'nullable|string|max:60000',
            'venue' => 'nullable|string|max:255',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'sales_starts_at' => 'required|date',
            'sales_ends_at' => 'required|date|after:sales_starts_at',
            'capacity' => 'nullable|integer|min:1|max:1000000',
            'max_per_order' => 'required|integer|min:1|max:50',
            'image' => 'nullable|image|max:4096',
        ], [
            'ends_at.after' => 'O fim do evento precisa ser depois do início.',
            'sales_ends_at.after' => 'O fim das vendas precisa ser depois do início.',
        ], [
            'title' => 'nome',
            'venue' => 'local',
            'starts_at' => 'início do evento',
            'ends_at' => 'fim do evento',
            'sales_starts_at' => 'início das vendas',
            'sales_ends_at' => 'fim das vendas',
            'capacity' => 'lotação',
            'max_per_order' => 'ingressos por pedido',
        ]);

        $data['image'] = ImageSource::resolve($request, 'image', $event?->image);

        return $data;
    }
}
