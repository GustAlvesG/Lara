{{-- Financeiro de um torneio: por categoria (recebido eRede e manual,
     estornado, líquido, a receber) e a lista de pagamentos, com CSV. --}}
@php
    use App\Models\Tournament\TournamentPayment as Payment;

    $money = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
    $th = 'px-5 py-3.5 text-left text-[11px] font-bold uppercase tracking-[0.08em] text-ink-3';
    $total = $summary['total'];
@endphp
<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title :title="'Financeiro — ' . $tournament->title" :back="route('tournaments.finance.index')">
            A conta é por inscrição: um pagamento pode cobrir várias categorias.
            <x-slot:actions>
                <x-secondary-button-a href="{{ route('tournaments.finance.csv', $tournament) }}"><x-icon name="download" /> CSV dos pagamentos</x-secondary-button-a>
            </x-slot:actions>
        </x-page-title>

        <section class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            @foreach (['received' => 'Recebido', 'refunded' => 'Estornado', 'net' => 'Líquido', 'pending_amount' => 'A receber'] as $key => $label)
                <div class="rounded-card bg-surface p-5 shadow-card">
                    <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-ink-3">{{ $label }}</p>
                    <p class="mt-1 font-mono text-lg font-semibold text-ink">{{ $money($total[$key]) }}</p>
                </div>
            @endforeach
        </section>

        <section class="flex flex-col gap-3">
            <h2 class="font-display text-lg font-semibold tracking-tight text-ink">Por categoria</h2>
            <div class="overflow-hidden rounded-card bg-surface shadow-card">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-line bg-subtle">
                                <th class="{{ $th }}">Categoria</th>
                                <th class="{{ $th }} text-right">Pagas</th>
                                <th class="{{ $th }} text-right">eRede</th>
                                <th class="{{ $th }} text-right">Manual</th>
                                <th class="{{ $th }} text-right">Estornado</th>
                                <th class="{{ $th }} text-right">Líquido</th>
                                <th class="{{ $th }} text-right">A receber</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($summary['categories'] as $row)
                                <tr>
                                    <td class="px-5 py-3.5 font-semibold text-ink">{{ $row['category']->name }}</td>
                                    <td class="px-5 py-3.5 text-right">{{ $row['paid_count'] }}@if ($row['cancelled_after_payment'])<span class="block text-xs text-ink-3">+{{ $row['cancelled_after_payment'] }} cancelada(s)</span>@endif</td>
                                    <td class="px-5 py-3.5 text-right font-mono">{{ $money($row['received_rede']) }}</td>
                                    <td class="px-5 py-3.5 text-right font-mono">{{ $money($row['received_manual']) }}</td>
                                    <td class="px-5 py-3.5 text-right font-mono">{{ $money($row['refunded']) }}</td>
                                    <td class="px-5 py-3.5 text-right font-mono font-semibold">{{ $money($row['net']) }}</td>
                                    <td class="px-5 py-3.5 text-right font-mono text-ink-3">{{ $money($row['pending_amount']) }}@if ($row['pending_count'])<span class="block text-xs">{{ $row['pending_count'] }} inscrição(ões)</span>@endif</td>
                                </tr>
                            @endforeach
                            <tr class="bg-subtle font-semibold">
                                <td class="px-5 py-3.5 text-ink">Total</td>
                                <td class="px-5 py-3.5 text-right">{{ $total['paid_count'] }}</td>
                                <td class="px-5 py-3.5 text-right font-mono">{{ $money($total['received_rede']) }}</td>
                                <td class="px-5 py-3.5 text-right font-mono">{{ $money($total['received_manual']) }}</td>
                                <td class="px-5 py-3.5 text-right font-mono">{{ $money($total['refunded']) }}</td>
                                <td class="px-5 py-3.5 text-right font-mono">{{ $money($total['net']) }}</td>
                                <td class="px-5 py-3.5 text-right font-mono">{{ $money($total['pending_amount']) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="flex flex-col gap-3">
            <h2 class="font-display text-lg font-semibold tracking-tight text-ink">Pagamentos ({{ $payments->count() }})</h2>
            @if ($payments->isEmpty())
                <x-empty-state icon="money">Nenhum pagamento ainda.</x-empty-state>
            @else
                @if ($payments->count() > 10)
                    <x-search-bar mode="client" target="#pagamentos" id="busca-pagamentos" placeholder="Buscar pessoa, CPF ou TID" />
                @endif
                <div class="overflow-hidden rounded-card bg-surface shadow-card">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-line bg-subtle">
                                    <th class="{{ $th }}">Data</th>
                                    <th class="{{ $th }}">Pessoa</th>
                                    <th class="{{ $th }}">Categorias</th>
                                    <th class="{{ $th }}">Forma</th>
                                    <th class="{{ $th }} text-right">Valor</th>
                                    <th class="{{ $th }} text-right">Estornado</th>
                                    <th class="{{ $th }} text-center">Situação</th>
                                </tr>
                            </thead>
                            <tbody id="pagamentos" class="divide-y divide-line">
                                @foreach ($payments as $payment)
                                    @php $first = $payment->registrations->first(); @endphp
                                    <tr data-search="{{ $first?->name }} {{ $first?->cpf }} {{ $payment->payment_integration_id }}">
                                        <td class="px-5 py-3.5 font-mono text-ink-2">{{ $payment->paid_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                        <td class="px-5 py-3.5 text-ink">{{ $first?->name }}</td>
                                        <td class="px-5 py-3.5 text-ink-2">{{ $payment->registrations->map(fn ($r) => $r->category?->name)->filter()->unique()->implode(', ') }}</td>
                                        <td class="px-5 py-3.5 text-ink-2">
                                            {{ \App\Services\Tournament\TournamentFinanceService::methodLabel($payment->payment_method) }}
                                            @if ($payment->is_manual)<span class="block text-xs text-ink-3">baixa manual{{ $payment->recordedBy ? ' por ' . $payment->recordedBy->name : '' }}</span>@elseif ($payment->payment_integration_id)<span class="block font-mono text-xs text-ink-3">TID {{ $payment->payment_integration_id }}</span>@endif
                                        </td>
                                        <td class="px-5 py-3.5 text-right font-mono">{{ $money($payment->paid_amount) }}</td>
                                        <td class="px-5 py-3.5 text-right font-mono">{{ (float) $payment->refunded_amount > 0 ? $money($payment->refunded_amount) : '—' }}</td>
                                        <td class="px-5 py-3.5 text-center"><x-pill :kind="$payment->status === Payment::STATUS_PAID ? 'ok' : 'warn'">{{ Payment::STATUS_LABELS[$payment->status] ?? $payment->status }}</x-pill></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </section>
    </x-page>
</x-app-layout>
