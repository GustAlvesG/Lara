<?php

namespace App\Http\Controllers\Ticket;

use App\Http\Controllers\Controller;
use App\Models\Ticket\TicketEvent;
use App\Models\Ticket\TicketMap;
use App\Models\Ticket\TicketSeat;
use App\Services\Ticket\TicketCaptiveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Mesas cativas de um mapa (`ingressos.gerenciar`): guardar um lugar para a
 * matrícula, tirar a cativa (de um lugar ou de todos) e o prazo de liberação
 * do evento ("N dias antes").
 */
class TicketCaptiveController extends Controller
{
    public function __construct(private TicketCaptiveService $captives)
    {
    }

    public function index(TicketEvent $event, TicketMap $map): View
    {
        return view('tickets.maps.captives', [
            'event' => $event,
            'map' => $map,
            'rows' => $this->captives->rows($map),
            'free' => $map->seats()->whereNull('captive_title')->orderBy('label')->get(['id', 'label', 'category']),
        ]);
    }

    /** Guarda um lugar (`seat_id`) para a matrícula, ou troca a matrícula dele. */
    public function store(Request $request, TicketEvent $event, TicketMap $map): RedirectResponse
    {
        $data = $request->validate([
            'seat_id' => 'required|integer',
            'captive_title' => 'required|string|max:20',
            'captive_name' => 'nullable|string|max:255',
        ], [], ['seat_id' => 'lugar', 'captive_title' => 'matrícula', 'captive_name' => 'nome']);

        $seat = $map->seats()->findOrFail($data['seat_id']);
        $seat = $this->captives->assign($seat, $data['captive_title'], $data['captive_name'] ?? null);

        return redirect()->route('tickets.maps.captives.index', [$event, $map])
            ->with('success', "{$seat->label} é mesa cativa da matrícula {$seat->captive_title}" . ($seat->captive_name ? " ({$seat->captive_name})" : '') . '.');
    }

    public function destroy(TicketEvent $event, TicketMap $map, TicketSeat $seat): RedirectResponse
    {
        abort_unless($seat->ticket_map_id === $map->id, 404);
        $this->captives->clear($seat);

        return redirect()->route('tickets.maps.captives.index', [$event, $map])->with('success', "{$seat->label} deixou de ser mesa cativa: está à venda para todos.");
    }

    public function destroyAll(TicketEvent $event, TicketMap $map): RedirectResponse
    {
        $count = $this->captives->clearAll($map);

        return redirect()->route('tickets.maps.captives.index', [$event, $map])->with('success', "{$count} mesa(s) cativa(s) liberada(s) para a venda geral.");
    }

    /** O prazo do evento: as cativas viram lugar comum N dias antes do início (vazio = não liberam sozinhas). */
    public function release(Request $request, TicketEvent $event, TicketMap $map): RedirectResponse
    {
        $data = $request->validate(['captive_release_days' => 'nullable|integer|min:0|max:365'], [], ['captive_release_days' => 'dias']);
        $event = $this->captives->setReleaseDays($event, isset($data['captive_release_days']) ? (int) $data['captive_release_days'] : null);
        $at = $event->captiveReleaseAt();

        return redirect()->route('tickets.maps.captives.index', [$event, $map])->with('success', $at
            ? 'As mesas cativas não compradas viram lugar comum em ' . $at->format('d/m/Y \à\s H\h') . '.'
            : 'As mesas cativas só deixam de ser cativas quando forem tiradas aqui.');
    }
}
