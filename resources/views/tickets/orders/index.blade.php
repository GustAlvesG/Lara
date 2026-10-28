{{-- Pedidos de um evento: busca por nome, CPF, código do ingresso ou número do
     pedido; filtro por quem vendeu (emitiu ou recebeu no balcão). --}}
@php
    use App\Authorization\Permissions as P;
    use App\Models\Ticket\TicketOrder;

    $field = 'h-11 rounded-full border border-line-strong bg-surface px-4 text-sm text-ink focus:border-grena focus:ring-4 focus:ring-grena-tint';
    $th = 'px-5 py-3.5 text-left text-[11px] font-bold uppercase tracking-[0.08em] text-ink-3';
    $money = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
    $kind = fn (string $status) => match ($status) {
        TicketOrder::STATUS_PAID => 'ok',
        TicketOrder::STATUS_PENDING_PAYMENT => 'info',
        TicketOrder::STATUS_CANCELLED => 'danger',
        default => 'off',
    };
@endphp
<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title :title="'Pedidos — ' . $event->title" :back="route('tickets.show', $event)">
            {{ $event->datesLabel() }}
            <x-slot:actions>
                @if (auth()->user()->can(P::INGRESSOS_PAGAMENTOS_BAIXA_MANUAL) || auth()->user()->can(P::INGRESSOS_CORTESIAS))
                    <x-primary-button-a href="{{ route('tickets.issue.create', $event) }}"><x-icon name="plus" /> Emitir ingressos</x-primary-button-a>
                @endif
            </x-slot:actions>
        </x-page-title>

        @include('partials.alerts')

        <x-search-bar :filters="['status', 'vendedor']" placeholder="Nome, CPF, código ING- ou nº do pedido">
            <x-slot:controls>
                <label for="filtro-situacao" class="sr-only">Situação</label>
                <select id="filtro-situacao" name="status" class="{{ $field }} w-full sm:w-56">
                    <option value="">Todas as situações</option>
                    @foreach (TicketOrder::STATUS_LABELS as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <label for="filtro-vendedor" class="sr-only">Vendido por</label>
                <select id="filtro-vendedor" name="vendedor" class="{{ $field }} w-full sm:w-56">
                    <option value="">Vendido por: todos</option>
                    <option value="site" @selected(request('vendedor') === 'site')>Site</option>
                    @foreach ($sellers as $seller)
                        <option value="{{ $seller->id }}" @selected((string) request('vendedor') === (string) $seller->id)>{{ $seller->name }}</option>
                    @endforeach
                </select>
            </x-slot:controls>
        </x-search-bar>

        @if ($orders->isEmpty())
            <x-empty-state icon="ticket">Nenhum pedido encontrado.</x-empty-state>
        @else
            <div class="overflow-hidden rounded-card bg-surface shadow-card">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-line bg-subtle">
                                <th class="{{ $th }}">Pedido</th>
                                <th class="{{ $th }}">Quem comprou</th>
                                <th class="{{ $th }}">Ingressos</th>
                                <th class="{{ $th }}">Total</th>
                                <th class="{{ $th }} text-center">Situação</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($orders as $order)
                                <tr>
                                    <td class="px-5 py-3.5">
                                        <a href="{{ route('tickets.orders.show', $order) }}" class="font-mono font-semibold text-ink no-underline hover:underline">#{{ $order->id }}</a>
                                        <p class="text-xs text-ink-3">{{ $order->created_at->format('d/m H:i') }} · {{ $order->origin === TicketOrder::ORIGIN_COUNTER ? 'Lara' . ($order->creator ? ' · ' . $order->creator->name : '') : 'site' }}</p>
                                        @if ($order->payment?->is_manual && $order->payment->recorded_by !== $order->created_by)<p class="text-xs text-ink-3">recebido por {{ $order->payment->recordedBy?->name ?? '—' }}</p>@endif
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <p class="font-semibold text-ink">{{ $order->buyer_name }}</p>
                                        <p class="text-xs text-ink-3">{{ TicketOrder::BUYER_LABELS[$order->buyer_kind] ?? '' }} · {{ $order->buyer_cpf ? \App\Support\Cpf::format($order->buyer_cpf) : 'sem CPF' }}</p>
                                    </td>
                                    <td class="px-5 py-3.5 text-ink-2">{{ $order->tickets->count() > 5 ? $order->tickets->count() . ' ingressos' : $order->tickets->pluck('holder_name')->implode(', ') }}</td>
                                    <td class="px-5 py-3.5 font-mono">
                                        {{ $order->courtesy ? 'Cortesia' : $money($order->total) }}
                                        @if ($order->coupon_code)<span class="block text-xs text-ink-3">cupom {{ $order->coupon_code }}</span>@endif
                                        @if ($order->payment?->is_manual)<span class="block text-xs text-ink-3">baixa manual</span>@endif
                                    </td>
                                    <td class="px-5 py-3.5 text-center"><x-pill :kind="$kind($order->status)">{{ TicketOrder::STATUS_LABELS[$order->status] ?? $order->status }}</x-pill></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            {{ $orders->links() }}
        @endif
    </x-page>
</x-app-layout>
