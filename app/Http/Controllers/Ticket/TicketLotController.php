<?php

namespace App\Http\Controllers\Ticket;

use App\Http\Controllers\Controller;
use App\Models\Ticket\TicketEvent;
use App\Models\Ticket\TicketLot;
use App\Models\Ticket\TicketType;
use App\Services\Ticket\TicketSetupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Lotes de um tipo de ingresso (permissão `ingressos.gerenciar`): quantidade,
 * janela e os dois preços. Preço de não sócio vazio = o lote é só para sócios.
 */
class TicketLotController extends Controller
{
    public function __construct(private TicketSetupService $setup)
    {
    }

    public function store(Request $request, TicketEvent $event, TicketType $type): RedirectResponse
    {
        $lot = $this->setup->saveLot($type, $this->validated($request) + ['active' => true], null, $request->boolean('force'));

        return redirect()->route('tickets.show', $event)->with('success', "{$lot->name} criado em {$type->name}.");
    }

    public function update(Request $request, TicketEvent $event, TicketType $type, TicketLot $lot): RedirectResponse
    {
        $this->setup->saveLot($type, $this->validated($request) + ['active' => $request->boolean('active')], $lot, $request->boolean('force'));

        return redirect()->route('tickets.show', $event)->with('success', "{$lot->name} atualizado.");
    }

    public function destroy(TicketEvent $event, TicketType $type, TicketLot $lot): RedirectResponse
    {
        $this->setup->deleteLot($lot);

        return redirect()->route('tickets.show', $event)->with('success', 'Lote apagado.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'sequence' => 'required|integer|min:1|max:999',
            'quantity' => 'required|integer|min:0|max:1000000',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after:starts_at',
            'price_member' => 'required|numeric|min:0|max:999999',
            'price_non_member' => 'nullable|numeric|min:0|max:999999',
        ], [
            'ends_at.after' => 'O fim do lote precisa ser depois do início.',
        ], [
            'name' => 'nome',
            'sequence' => 'ordem',
            'quantity' => 'quantidade',
            'starts_at' => 'início do lote',
            'ends_at' => 'fim do lote',
            'price_member' => 'preço sócio',
            'price_non_member' => 'preço não sócio',
        ]);

        $data['price_non_member'] = ($data['price_non_member'] ?? null) === null || $data['price_non_member'] === '' ? null : $data['price_non_member'];

        return $data;
    }
}
