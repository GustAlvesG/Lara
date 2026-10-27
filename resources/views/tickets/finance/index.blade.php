{{-- Ingressos › Financeiro: eventos do ano com vendidos, descontos e recebido. --}}
@php
    $money = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
    $th = 'px-5 py-3.5 text-left text-[11px] font-bold uppercase tracking-[0.08em] text-ink-3';
@endphp
<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title title="Ingressos — Financeiro">
            Vendidos, descontos de cupom, cortesias, recebido e estornado por evento. Recebido = pagamentos (eRede e baixa manual) menos estornos.
        </x-page-title>

        <form method="GET" class="flex items-end gap-2">
            <div><x-input-label for="ano" value="Ano" /><x-text-input type="number" id="ano" name="ano" class="mt-1 w-32" :value="$year" /></div>
            <x-secondary-button type="submit">Ver</x-secondary-button>
        </form>

        @if ($rows->isEmpty())
            <x-empty-state icon="money">Nenhum evento em {{ $year }}.</x-empty-state>
        @else
            <div class="overflow-hidden rounded-card bg-surface shadow-card">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-line bg-subtle">
                                <th class="{{ $th }}">Evento</th>
                                <th class="{{ $th }}">Vendidos</th>
                                <th class="{{ $th }}">Cortesias</th>
                                <th class="{{ $th }}">Descontos</th>
                                <th class="{{ $th }}">Estornado</th>
                                <th class="{{ $th }}">Recebido</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($rows as $row)
                                <tr>
                                    <td class="px-5 py-3.5">
                                        <a href="{{ route('tickets.finance.show', $row['event']) }}" class="font-semibold text-ink no-underline hover:underline">{{ $row['event']->title }}</a>
                                        <p class="text-xs text-ink-3">{{ $row['event']->datesLabel() }}</p>
                                    </td>
                                    <td class="px-5 py-3.5 font-mono">{{ $row['sold'] }}</td>
                                    <td class="px-5 py-3.5 font-mono">{{ $row['courtesies'] }}</td>
                                    <td class="px-5 py-3.5 font-mono">{{ $money($row['discount']) }}</td>
                                    <td class="px-5 py-3.5 font-mono">{{ $money($row['refunded']) }}</td>
                                    <td class="px-5 py-3.5 font-mono font-bold">{{ $money($row['received']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </x-page>
</x-app-layout>
