<?php

namespace App\Http\Controllers\Ticket;

use App\Http\Controllers\Controller;
use App\Models\Ticket\TicketEvent;
use App\Models\Ticket\TicketType;
use App\Services\Ticket\TicketSetupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Tipos de ingresso do evento (permissão `ingressos.gerenciar`). No evento de
 * vários dias, o tipo com dia é diária; sem dia, passaporte. Com mapa, o tipo
 * é de lugar marcado (mesa, bistrô, camarote).
 */
class TicketTypeController extends Controller
{
    public function __construct(private TicketSetupService $setup)
    {
    }

    public function store(Request $request, TicketEvent $event): RedirectResponse
    {
        $type = $this->setup->saveType($event, $this->validated($request) + ['active' => true]);

        return redirect()->route('tickets.show', $event)->with('success', "Tipo {$type->name} criado. Cadastre os lotes dele.");
    }

    public function update(Request $request, TicketEvent $event, TicketType $type): RedirectResponse
    {
        $this->setup->saveType($event, $this->validated($request) + ['active' => $request->boolean('active')], $type);

        return redirect()->route('tickets.show', $event)->with('success', "Tipo {$type->name} atualizado.");
    }

    public function destroy(TicketEvent $event, TicketType $type): RedirectResponse
    {
        $this->setup->deleteType($type);

        return redirect()->route('tickets.show', $event)->with('success', 'Tipo apagado.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'sort_order' => 'nullable|integer|min:0|max:999',
            // Evento de vários dias: o dia da diária; vazio = passaporte.
            'ticket_event_day_id' => 'nullable|integer',
            // Lugar marcado: o mapa (vazio = ingresso de entrada) e, se só parte dos lugares, a categoria.
            'ticket_map_id' => 'nullable|integer',
            'seat_category' => 'nullable|string|max:60',
        ], [], ['name' => 'nome', 'description' => 'descrição', 'sort_order' => 'ordem', 'ticket_event_day_id' => 'dia', 'ticket_map_id' => 'mapa', 'seat_category' => 'categoria']);

        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        return $data;
    }
}
