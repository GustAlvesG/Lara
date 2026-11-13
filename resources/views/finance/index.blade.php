{{-- Financeiro › Visão geral: o período em números. Recebido, estornado e
     líquido de Reservas, Torneios e Ingressos (FinanceLedger::summary), por
     origem e por forma, eRede × baixa manual; e o que saiu e falta sair para
     os freelancers. Cada número leva à lista de Recebimentos filtrada. --}}
@php
    use App\Services\Finance\FinanceLedger;

    $money = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
    $th = 'px-4 py-3 text-left text-[11px] font-bold uppercase tracking-[0.08em] text-ink-3';
    $thr = 'px-4 py-3 text-right text-[11px] font-bold uppercase tracking-[0.08em] text-ink-3';
    $label = 'text-[11px] font-bold uppercase tracking-[0.08em] text-ink-3';
    $from = $filters['from'];
    $to = $filters['to'];
    $period = $from->isSameDay($to) ? $from->format('d/m/Y') : $from->format('d/m/Y') . ' a ' . $to->format('d/m/Y');
    $total = $summary['total'];
    $receipts = fn (array $extra = []) => route('finance.receipts.index', $query + $extra);
@endphp
<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title title="Financeiro">
            Recebimentos de Reservas, Torneios e Ingressos e pagamentos a freelancers — {{ $period }}.
            <x-slot:actions>
                <x-secondary-button-a href="{{ route('finance.receipts.csv', $query) }}"><x-icon name="download" /> CSV dos recebimentos</x-secondary-button-a>
            </x-slot:actions>
        </x-page-title>

        <form method="GET" class="grid grid-cols-1 gap-4 rounded-card bg-surface p-5 shadow-card sm:grid-cols-4 sm:items-end">
            <div><x-input-label for="fin-from" value="De" /><x-text-input type="date" id="fin-from" name="de" class="mt-1" :value="$from->format('Y-m-d')" /></div>
            <div><x-input-label for="fin-to" value="Até" /><x-text-input type="date" id="fin-to" name="ate" class="mt-1" :value="$to->format('Y-m-d')" /></div>
            <div class="text-sm text-ink-3">Pela data do pagamento. Sem datas, o mês corrente.</div>
            <div class="sm:text-right"><x-primary-button>Ver</x-primary-button></div>
        </form>

        <section class="grid grid-cols-2 gap-4 rounded-card bg-surface p-5 shadow-card lg:grid-cols-5">
            <div><p class="{{ $label }}">Recebido</p><p class="mt-1 font-mono text-2xl font-bold text-ink">{{ $money($total['paid']) }}</p><p class="text-xs text-ink-3">{{ $total['count'] }} pagamento(s)</p></div>
            <div><p class="{{ $label }}">Estornado</p><p class="mt-1 font-mono text-xl text-danger">{{ $money($total['refunded']) }}</p></div>
            <div><p class="{{ $label }}">Líquido</p><p class="mt-1 font-mono text-2xl font-bold text-ink">{{ $money($total['net']) }}</p></div>
            <div><p class="{{ $label }}">Site (eRede)</p><a href="{{ $receipts(['forma' => FinanceLedger::METHOD_ONLINE]) }}" class="mt-1 block font-mono text-xl text-ink hover:underline">{{ $money($total['online']) }}</a></div>
            <div><p class="{{ $label }}">Baixa manual</p><a href="{{ $receipts(['forma' => FinanceLedger::METHOD_MANUAL]) }}" class="mt-1 block font-mono text-xl text-ink hover:underline">{{ $money($total['manual']) }}</a><p class="text-xs text-ink-3">recebido no balcão</p></div>
        </section>

        <section class="flex flex-col gap-3">
            <h2 class="font-display text-lg font-semibold tracking-tight text-ink">Por origem</h2>
            <div class="overflow-x-auto rounded-card bg-surface shadow-card">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-line bg-subtle">
                            <th class="{{ $th }}">Origem</th><th class="{{ $thr }}">Pagamentos</th><th class="{{ $thr }}">Site (eRede)</th><th class="{{ $thr }}">Baixa manual</th><th class="{{ $thr }}">Recebido</th><th class="{{ $thr }}">Estornado</th><th class="{{ $thr }}">Líquido</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($summary['origins'] as $origin => $row)
                            <tr>
                                <td class="px-4 py-3"><a href="{{ $receipts(['origem' => $origin]) }}" class="font-semibold text-grena-ink hover:underline">{{ FinanceLedger::ORIGINS[$origin] }}</a></td>
                                <td class="px-4 py-3 text-right font-mono">{{ $row['count'] }}</td>
                                <td class="px-4 py-3 text-right font-mono">{{ $money($row['online']) }}</td>
                                <td class="px-4 py-3 text-right font-mono">{{ $money($row['manual']) }}</td>
                                <td class="px-4 py-3 text-right font-mono font-semibold">{{ $money($row['paid']) }}</td>
                                <td class="px-4 py-3 text-right font-mono text-danger">{{ $row['refunded'] > 0 ? $money($row['refunded']) : '—' }}</td>
                                <td class="px-4 py-3 text-right font-mono font-bold">{{ $money($row['net']) }}</td>
                            </tr>
                        @endforeach
                        <tr class="bg-subtle font-bold">
                            <td class="px-4 py-3">Total</td>
                            <td class="px-4 py-3 text-right font-mono">{{ $total['count'] }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ $money($total['online']) }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ $money($total['manual']) }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ $money($total['paid']) }}</td>
                            <td class="px-4 py-3 text-right font-mono text-danger">{{ $total['refunded'] > 0 ? $money($total['refunded']) : '—' }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ $money($total['net']) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="flex flex-col gap-3">
            <h2 class="font-display text-lg font-semibold tracking-tight text-ink">Por forma de pagamento</h2>
            @if ($summary['methods'] === [])
                <x-empty-state icon="money">Nenhum recebimento neste período.</x-empty-state>
            @else
                <div class="overflow-x-auto rounded-card bg-surface shadow-card">
                    <table class="w-full text-sm">
                        <thead><tr class="border-b border-line bg-subtle"><th class="{{ $th }}">Forma</th><th class="{{ $thr }}">Pagamentos</th><th class="{{ $thr }}">Recebido</th><th class="{{ $thr }}">Estornado</th><th class="{{ $thr }}">Líquido</th></tr></thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($summary['methods'] as $method => $row)
                                <tr>
                                    <td class="px-4 py-3">
                                        <a href="{{ $receipts(['forma' => $method]) }}" class="font-semibold text-grena-ink hover:underline">{{ $row['label'] }}</a>
                                        <span class="ml-1 text-[11px] font-bold uppercase text-ink-3">{{ $row['manual'] ? 'Baixa manual' : 'Site' }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono">{{ $row['count'] }}</td>
                                    <td class="px-4 py-3 text-right font-mono">{{ $money($row['paid']) }}</td>
                                    <td class="px-4 py-3 text-right font-mono text-danger">{{ $row['refunded'] > 0 ? $money($row['refunded']) : '—' }}</td>
                                    <td class="px-4 py-3 text-right font-mono font-semibold">{{ $money($row['net']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="grid grid-cols-1 gap-4 rounded-card bg-surface p-5 shadow-card sm:grid-cols-3 sm:items-center">
            <div>
                <h2 class="font-display text-lg font-semibold tracking-tight text-ink">Freelancers</h2>
                <p class="text-xs text-ink-3">O que sai: contratos aprovados pela diretoria.</p>
            </div>
            <div><p class="{{ $label }}">Pago no período</p><p class="mt-1 font-mono text-xl text-ink">{{ $money($freelancers['paid']) }}</p><p class="text-xs text-ink-3">{{ $freelancers['paid_count'] }} contrato(s)</p></div>
            <div>
                <p class="{{ $label }}">A pagar (hoje)</p><p class="mt-1 font-mono text-xl text-ink">{{ $money($freelancers['pending']) }}</p>
                <p class="text-xs text-ink-3">{{ $freelancers['pending_count'] }} contrato(s)@if ($canFreelancers) · <a href="{{ route('freelancer-services.finance') }}" class="font-bold text-grena-ink hover:underline">ver os lotes</a>@endif</p>
            </div>
        </section>
    </x-page>
</x-app-layout>
