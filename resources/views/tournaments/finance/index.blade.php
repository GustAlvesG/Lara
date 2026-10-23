{{-- Torneios › Financeiro: os torneios (fora rascunho) com recebido,
     estornado, líquido e a receber; filtro por ano de início. --}}
@php
    $money = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
    $field = 'h-11 rounded-full border border-line-strong bg-surface px-4 text-sm text-ink focus:border-grena focus:ring-4 focus:ring-grena-tint';
    $th = 'px-5 py-3.5 text-left text-[11px] font-bold uppercase tracking-[0.08em] text-ink-3';
@endphp
<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title title="Financeiro dos torneios">
            Recebido pela eRede e por baixa manual, estornos e o que falta receber. A conta é por inscrição.
        </x-page-title>

        <form method="GET" class="flex flex-wrap items-center gap-3">
            <label for="filtro-ano" class="sr-only">Ano</label>
            <select id="filtro-ano" name="ano" class="{{ $field }} w-full sm:w-44" onchange="this.form.submit()">
                <option value="">Todos os anos</option>
                @foreach ($years as $y)
                    <option value="{{ $y }}" @selected($year === $y)>{{ $y }}</option>
                @endforeach
            </select>
        </form>

        <section class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            @foreach (['received' => 'Recebido', 'refunded' => 'Estornado', 'net' => 'Líquido', 'pending_amount' => 'A receber'] as $key => $label)
                <div class="rounded-card bg-surface p-5 shadow-card">
                    <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-ink-3">{{ $label }}</p>
                    <p class="mt-1 font-mono text-lg font-semibold text-ink">{{ $money($totals[$key]) }}</p>
                </div>
            @endforeach
        </section>

        @if ($rows->isEmpty())
            <x-empty-state icon="money">Nenhum torneio publicado{{ $year ? " em {$year}" : '' }}.</x-empty-state>
        @else
            <div class="overflow-hidden rounded-card bg-surface shadow-card">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-line bg-subtle">
                                <th class="{{ $th }}">Torneio</th>
                                <th class="{{ $th }} text-right">Recebido</th>
                                <th class="{{ $th }} text-right">Estornado</th>
                                <th class="{{ $th }} text-right">Líquido</th>
                                <th class="{{ $th }} text-right">A receber</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($rows as $row)
                                <tr>
                                    <td class="px-5 py-3.5">
                                        <a href="{{ route('tournaments.finance.show', $row['tournament']) }}" class="font-semibold text-ink no-underline hover:underline">{{ $row['tournament']->title }}</a>
                                        <p class="text-xs text-ink-3">{{ $row['tournament']->placeGroup?->name }} · {{ $row['tournament']->starts_on->format('d/m/Y') }} · {{ $row['paid_count'] }} inscrição(ões) paga(s)</p>
                                    </td>
                                    <td class="px-5 py-3.5 text-right font-mono">{{ $money($row['received']) }}</td>
                                    <td class="px-5 py-3.5 text-right font-mono">{{ $money($row['refunded']) }}</td>
                                    <td class="px-5 py-3.5 text-right font-mono font-semibold">{{ $money($row['net']) }}</td>
                                    <td class="px-5 py-3.5 text-right font-mono text-ink-3">{{ $money($row['pending_amount']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </x-page>
</x-app-layout>
