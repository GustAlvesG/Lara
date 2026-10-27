<?php

namespace App\Http\Controllers\Ticket;

use App\Http\Controllers\Controller;
use App\Models\Ticket\TicketEvent;
use App\Models\Ticket\TicketEventDay;
use App\Services\Ticket\TicketSetupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Dias do evento de vários dias (permissão `ingressos.gerenciar`). Com dias
 * cadastrados, cada tipo de ingresso é a diária de um dia ou, sem dia, o
 * passaporte.
 */
class TicketDayController extends Controller
{
    public function __construct(private TicketSetupService $setup)
    {
    }

    public function store(Request $request, TicketEvent $event): RedirectResponse
    {
        $day = $this->setup->saveDay($event, $this->validated($request));

        return redirect()->route('tickets.show', $event)->with('success', "Dia {$day->label()} cadastrado. Crie as diárias dele (tipo com este dia) e, se houver, o passaporte (tipo sem dia).");
    }

    public function update(Request $request, TicketEvent $event, TicketEventDay $day): RedirectResponse
    {
        $this->setup->saveDay($event, $this->validated($request), $day);

        return redirect()->route('tickets.show', $event)->with('success', "Dia {$day->label()} atualizado.");
    }

    public function destroy(TicketEvent $event, TicketEventDay $day): RedirectResponse
    {
        $this->setup->deleteDay($day);

        return redirect()->route('tickets.show', $event)->with('success', 'Dia apagado.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'nullable|string|max:100',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
        ], [
            'ends_at.after' => 'O fim do dia precisa ser depois do início.',
        ], [
            'name' => 'nome',
            'starts_at' => 'início do dia',
            'ends_at' => 'fim do dia',
        ]);
    }
}
