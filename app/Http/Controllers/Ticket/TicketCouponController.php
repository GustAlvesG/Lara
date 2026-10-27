<?php

namespace App\Http\Controllers\Ticket;

use App\Http\Controllers\Controller;
use App\Models\Ticket\TicketCoupon;
use App\Models\Ticket\TicketEvent;
use App\Models\Ticket\TicketLot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Cupons de desconto de um evento (permissão `ingressos.cupons`). Cupom já
 * usado não é apagado (o pedido guarda o vínculo): é desativado.
 */
class TicketCouponController extends Controller
{
    public function index(TicketEvent $event): View
    {
        $event->load('types', 'coupons');

        return view('tickets.coupons', [
            'event' => $event,
            'uses' => $event->coupons->mapWithKeys(fn (TicketCoupon $c) => [$c->id => $c->usedCount()]),
        ]);
    }

    public function store(Request $request, TicketEvent $event): RedirectResponse
    {
        $coupon = $event->coupons()->create($this->validated($request, $event) + ['created_by' => $request->user()->id, 'active' => true]);

        return redirect()->route('tickets.coupons.index', $event)->with('success', "Cupom {$coupon->code} criado.");
    }

    public function update(Request $request, TicketEvent $event, TicketCoupon $coupon): RedirectResponse
    {
        $coupon->update($this->validated($request, $event, $coupon) + ['active' => $request->boolean('active')]);

        return redirect()->route('tickets.coupons.index', $event)->with('success', "Cupom {$coupon->code} atualizado.");
    }

    public function destroy(TicketEvent $event, TicketCoupon $coupon): RedirectResponse
    {
        if ($coupon->orders()->exists()) {
            throw ValidationException::withMessages(['coupon' => "O cupom {$coupon->code} já foi usado em pedido: desative em vez de apagar."]);
        }

        $coupon->delete();

        return redirect()->route('tickets.coupons.index', $event)->with('success', 'Cupom apagado.');
    }

    private function validated(Request $request, TicketEvent $event, ?TicketCoupon $coupon = null): array
    {
        $request->merge(['code' => TicketCoupon::normalizeCode($request->input('code'))]);

        $data = $request->validate([
            'code' => [
                'required', 'string', 'max:40', 'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('ticket_coupons', 'code')->where('ticket_event_id', $event->id)->ignore($coupon?->id),
            ],
            'description' => 'nullable|string|max:255',
            'kind' => ['required', Rule::in(array_keys(TicketCoupon::KIND_LABELS))],
            'value' => 'required|numeric|min:0.01|max:999999',
            'audience' => ['required', Rule::in(array_keys(TicketCoupon::AUDIENCE_LABELS))],
            'ticket_type_ids' => 'nullable|array',
            'ticket_type_ids.*' => ['integer', Rule::in($event->types()->pluck('id')->all())],
            'max_uses' => 'nullable|integer|min:1|max:1000000',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after:starts_at',
        ], [
            'code.regex' => 'O código usa só letras, números, hífen e sublinhado.',
            'code.unique' => 'Já existe um cupom com este código neste evento.',
            'ends_at.after' => 'O fim do cupom precisa ser depois do início.',
        ], [
            'code' => 'código',
            'kind' => 'tipo de desconto',
            'value' => 'valor',
            'audience' => 'público',
            'max_uses' => 'limite de usos',
        ]);

        if ($data['kind'] === TicketCoupon::KIND_PERCENT && (float) $data['value'] > 100) {
            throw ValidationException::withMessages(['value' => 'A porcentagem vai até 100.']);
        }

        $data['ticket_type_ids'] = array_values(array_map('intval', $data['ticket_type_ids'] ?? [])) ?: null;
        $data['max_uses'] = $data['max_uses'] ?? null;

        return $data;
    }
}
