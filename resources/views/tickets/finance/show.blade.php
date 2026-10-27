{{-- Financeiro de um evento: totais, por tipo e lote, por forma de pagamento e por cupom. --}}
@php
    $money = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
    $th = 'px-4 py-3 text-left text-[11px] font-bold uppercase tracking-[0.08em] text-ink-3';
    $label = 'text-[11px] font-bold uppercase tracking-[0.08em] text-ink-3';
@endphp
<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title :title="'Financeiro — ' . $event->title" :back="route('tickets.finance.index')">
            {{ $event->datesLabel() }}
            <x-slot:actions>
                <x-secondary-button-a href="{{ route('tickets.finance.csv', $event) }}"><x-icon name="download" /> CSV dos ingressos</x-secondary-button-a>
            </x-slot:actions>
        </x-page-title>

        <section class="grid grid-cols-2 gap-4 rounded-card bg-surface p-5 shadow-card sm:grid-cols-4">
            <div><p class="{{ $label }}">Vendidos</p><p class="mt-1 font-mono text-2xl text-ink">{{ $totals['sold'] }}</p><p class="text-xs text-ink-3">{{ $totals['used'] }} entraram · {{ $totals['courtesies'] }} cortesia(s)</p></div>
            <div><p class="{{ $label }}">Valor de face</p><p class="mt-1 font-mono text-2xl text-ink">{{ $money($totals['face']) }}</p><p class="text-xs text-ink-3">sem as cortesias</p></div>
            <div><p class="{{ $label }}">Descontos</p><p class="mt-1 font-mono text-2xl text-ink">{{ $money($totals['discount']) }}</p><p class="text-xs text-ink-3">cupons</p></div>
            <div><p class="{{ $label }}">Recebido</p><p class="mt-1 font-mono text-2xl font-bold text-ink">{{ $money($totals['received']) }}</p><p class="text-xs text-ink-3">pago {{ $money($totals['paid']) }} − estornado {{ $money($totals['refunded']) }} · {{ $totals['cancelled'] }} cancelado(s)</p></div>
        </section>

        <section class="flex flex-col gap-3">
            <h2 class="font-display text-lg font-semibold tracking-tight text-ink">Por tipo e lote</h2>
            @if ($byLot->isEmpty())
                <x-empty-state icon="ticket">Nenhum ingresso vendido.</x-empty-state>
            @else
                <div class="overflow-x-auto rounded-card bg-surface shadow-card">
                    <table class="w-full text-sm">
                        <thead><tr class="border-b border-line bg-subtle"><th class="{{ $th }}">Tipo</th><th class="{{ $th }}">Lote</th><th class="{{ $th }}">Público</th><th class="{{ $th }}">Ingressos</th><th class="{{ $th }}">Cortesias</th><th class="{{ $th }}">Descontos</th><th class="{{ $th }}">Cobrado</th></tr></thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($byLot as $row)
                                <tr>
                                    <td class="px-4 py-3 font-semibold text-ink">{{ $row['type'] }}</td>
                                    <td class="px-4 py-3 text-ink-2">{{ $row['lot'] }}</td>
                                    <td class="px-4 py-3 text-ink-2">{{ $row['price_kind'] }}</td>
                                    <td class="px-4 py-3 font-mono">{{ $row['count'] }}</td>
                                    <td class="px-4 py-3 font-mono">{{ $row['courtesies'] }}</td>
                                    <td class="px-4 py-3 font-mono">{{ $money($row['discount']) }}</td>
                                    <td class="px-4 py-3 font-mono">{{ $money($row['net']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <section class="flex flex-col gap-3">
                <h2 class="font-display text-lg font-semibold tracking-tight text-ink">Por forma de pagamento</h2>
                @if ($byMethod->isEmpty())
                    <x-empty-state icon="money">Nenhum pagamento.</x-empty-state>
                @else
                    <div class="overflow-x-auto rounded-card bg-surface shadow-card">
                        <table class="w-full text-sm">
                            <thead><tr class="border-b border-line bg-subtle"><th class="{{ $th }}">Forma</th><th class="{{ $th }}">Pedidos</th><th class="{{ $th }}">Pago</th><th class="{{ $th }}">Estornado</th></tr></thead>
                            <tbody class="divide-y divide-line">
                                @foreach ($byMethod as $row)
                                    <tr><td class="px-4 py-3 text-ink">{{ $row['method'] }}</td><td class="px-4 py-3 font-mono">{{ $row['count'] }}</td><td class="px-4 py-3 font-mono">{{ $money($row['paid']) }}</td><td class="px-4 py-3 font-mono">{{ $money($row['refunded']) }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <section class="flex flex-col gap-3">
                <h2 class="font-display text-lg font-semibold tracking-tight text-ink">Cupons</h2>
                @if ($byCoupon->isEmpty())
                    <x-empty-state icon="tag">Nenhum cupom usado.</x-empty-state>
                @else
                    <div class="overflow-x-auto rounded-card bg-surface shadow-card">
                        <table class="w-full text-sm">
                            <thead><tr class="border-b border-line bg-subtle"><th class="{{ $th }}">Cupom</th><th class="{{ $th }}">Ingressos</th><th class="{{ $th }}">Desconto</th></tr></thead>
                            <tbody class="divide-y divide-line">
                                @foreach ($byCoupon as $row)
                                    <tr><td class="px-4 py-3 font-mono font-bold text-ink">{{ $row['code'] }}</td><td class="px-4 py-3 font-mono">{{ $row['count'] }}</td><td class="px-4 py-3 font-mono">{{ $money($row['discount']) }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>
    </x-page>
</x-app-layout>
