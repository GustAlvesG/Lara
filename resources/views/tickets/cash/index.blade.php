{{-- Relatório de caixa dos ingressos: por dia, o que cada pessoa recebeu no
     balcão em cada forma de pagamento, cortesias e cancelamentos; o site
     (eRede) à parte, só na visão de todos. Lançamentos um a um no fim. --}}
@php
    use App\Services\Ticket\TicketCashService;
    use App\Models\SchedulePayment;

    $money = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
    $th = 'px-4 py-3 text-left text-[11px] font-bold uppercase tracking-[0.08em] text-ink-3';
    $label = 'text-[11px] font-bold uppercase tracking-[0.08em] text-ink-3';
    $select = 'mt-1 h-11 w-full rounded-xl border border-line-strong bg-surface px-3 text-ink';
    $from = $report['from'];
    $to = $report['to'];
    $query = array_filter(['de' => $from->format('Y-m-d'), 'ate' => $to->format('Y-m-d'), 'pessoa' => $userId]);
    $period = $from->isSameDay($to) ? $from->format('d/m/Y') : $from->format('d/m/Y') . ' a ' . $to->format('d/m/Y');
    $totals = $report['totals'];
@endphp
<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title title="Caixa dos ingressos">
            {{ $all ? 'O que cada pessoa recebeu no balcão' : 'O que você recebeu no balcão' }}, por forma de pagamento — {{ $period }}. Para o fechamento de caixa.
            <x-slot:actions>
                <x-secondary-button-a href="{{ route('tickets.cash.pdf', $query) }}"><x-icon name="download" /> PDF</x-secondary-button-a>
                <x-secondary-button-a href="{{ route('tickets.cash.csv', $query) }}"><x-icon name="download" /> CSV</x-secondary-button-a>
            </x-slot:actions>
        </x-page-title>

        @if ($errors->any())
            <div class="rounded-2xl bg-danger-soft px-5 py-4 text-sm text-danger" role="alert">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif

        <form method="GET" class="grid grid-cols-1 gap-4 rounded-card bg-surface p-5 shadow-card sm:grid-cols-4 sm:items-end">
            <div><x-input-label for="cash-from" value="De" /><x-text-input type="date" id="cash-from" name="de" class="mt-1" :value="$from->format('Y-m-d')" /></div>
            <div><x-input-label for="cash-to" value="Até" /><x-text-input type="date" id="cash-to" name="ate" class="mt-1" :value="$to->format('Y-m-d')" /></div>
            @if ($all)
                <div>
                    <x-input-label for="cash-person" value="Pessoa" />
                    <select id="cash-person" name="pessoa" class="{{ $select }}">
                        <option value="">Todas</option>
                        @foreach ($people as $person)
                            <option value="{{ $person->id }}" @selected($userId === $person->id)>{{ $person->name }}</option>
                        @endforeach
                    </select>
                </div>
            @else
                <div class="text-sm text-ink-3">Você vê só o seu caixa.</div>
            @endif
            <div class="sm:text-right"><x-primary-button>Ver</x-primary-button></div>
        </form>

        <section class="grid grid-cols-2 gap-4 rounded-card bg-surface p-5 shadow-card sm:grid-cols-4">
            <div><p class="{{ $label }}">Recebido no balcão</p><p class="mt-1 font-mono text-2xl font-bold text-ink">{{ $money($totals['total']) }}</p><p class="text-xs text-ink-3">{{ $totals['sales'] }} venda(s) · {{ $totals['tickets'] }} ingresso(s)</p></div>
            @foreach ($report['methods'] as $method => $methodLabel)
                <div><p class="{{ $label }}">{{ $methodLabel }}</p><p class="mt-1 font-mono text-xl text-ink">{{ $money($totals['methods'][$method] ?? 0) }}</p></div>
            @endforeach
            <div><p class="{{ $label }}">Cortesias</p><p class="mt-1 font-mono text-xl text-ink">{{ $totals['courtesies'] }}</p><p class="text-xs text-ink-3">ingresso(s), sem valor</p></div>
            <div><p class="{{ $label }}">Cancelados</p><p class="mt-1 font-mono text-xl text-ink">{{ $totals['cancelled'] }}</p><p class="text-xs text-ink-3">{{ $money($totals['cancelled_amount']) }} em ingressos de baixa manual (devolução, se houve, por fora)</p></div>
        </section>

        @forelse ($report['days'] as $day => $rows)
            <section class="flex flex-col gap-3">
                <h2 class="font-display text-lg font-semibold tracking-tight text-ink">{{ \Illuminate\Support\Carbon::parse($day)->translatedFormat('d/m/Y · l') }}</h2>
                <div class="overflow-x-auto rounded-card bg-surface shadow-card">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-line bg-subtle">
                                <th class="{{ $th }}">Pessoa</th>
                                @foreach ($report['methods'] as $methodLabel)<th class="{{ $th }}">{{ $methodLabel }}</th>@endforeach
                                <th class="{{ $th }}">Total</th>
                                <th class="{{ $th }}">Vendas · ingressos</th>
                                <th class="{{ $th }}">Cortesias</th>
                                <th class="{{ $th }}">Cancelados</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($rows as $row)
                                <tr>
                                    <td class="px-4 py-3 font-semibold text-ink">{{ $row['user']?->name ?? '—' }}</td>
                                    @foreach (array_keys($report['methods']) as $method)<td class="px-4 py-3 font-mono">{{ $money($row['methods'][$method] ?? 0) }}</td>@endforeach
                                    <td class="px-4 py-3 font-mono font-bold">{{ $money($row['total']) }}</td>
                                    <td class="px-4 py-3 font-mono">{{ $row['sales'] }} · {{ $row['tickets'] }}</td>
                                    <td class="px-4 py-3 font-mono">{{ $row['courtesies'] }}</td>
                                    <td class="px-4 py-3 font-mono">{{ $row['cancelled'] }}@if ($row['cancelled_amount'] > 0) <span class="text-xs text-ink-3">({{ $money($row['cancelled_amount']) }})</span>@endif</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @empty
            <x-empty-state icon="money">Nenhuma venda, cortesia ou cancelamento no balcão {{ $from->isSameDay($to) ? 'neste dia' : 'neste período' }}.</x-empty-state>
        @endforelse

        @if ($report['site'])
            <section class="rounded-card bg-surface p-5 shadow-card">
                <h2 class="mb-1 text-sm font-black uppercase tracking-widest text-ink-3">Site (eRede)</h2>
                <p class="text-sm text-ink-2">{{ $report['site']['orders'] }} pedido(s) · {{ $report['site']['tickets'] }} ingresso(s) · {{ $money($report['site']['amount']) }} pagos @if ($report['site']['refunded'] > 0)· {{ $money($report['site']['refunded']) }} estornados @endif</p>
                <p class="text-xs text-ink-3">Pago pelo cartão no site: cai na conta pela eRede, não passa pelo caixa de ninguém.</p>
            </section>
        @endif

        @if ($report['entries'] !== [])
            <section class="flex flex-col gap-3">
                <h2 class="font-display text-lg font-semibold tracking-tight text-ink">Lançamentos</h2>
                <div class="overflow-x-auto rounded-card bg-surface shadow-card">
                    <table class="w-full text-sm">
                        <thead><tr class="border-b border-line bg-subtle"><th class="{{ $th }}">Quando</th><th class="{{ $th }}">Pessoa</th><th class="{{ $th }}">Lançamento</th><th class="{{ $th }}">Evento</th><th class="{{ $th }}">Pedido</th><th class="{{ $th }}">Ingressos</th><th class="{{ $th }}">Valor</th></tr></thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($report['entries'] as $e)
                                <tr>
                                    <td class="px-4 py-3 font-mono text-ink-2">{{ $e['at']->format('d/m H:i') }}</td>
                                    <td class="px-4 py-3 text-ink">{{ $e['user']?->name ?? '—' }}</td>
                                    <td class="px-4 py-3 text-ink-2">{{ TicketCashService::KIND_LABELS[$e['kind']] }}@if ($e['method']) · {{ SchedulePayment::MANUAL_METHODS[$e['method']] ?? $e['method'] }}@endif</td>
                                    <td class="px-4 py-3 text-ink-2">{{ $e['event'] }}</td>
                                    <td class="px-4 py-3"><a href="{{ route('tickets.orders.show', $e['order_id']) }}" class="font-mono text-grena hover:underline">#{{ $e['order_id'] }}</a> <span class="block text-xs text-ink-3">{{ $e['buyer'] }}</span></td>
                                    <td class="px-4 py-3 font-mono">{{ $e['tickets'] }}</td>
                                    <td class="px-4 py-3 font-mono">{{ $e['kind'] === TicketCashService::KIND_COURTESY ? '—' : ($e['kind'] === TicketCashService::KIND_CANCEL ? '− ' : '') . $money($e['amount']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </x-page>
</x-app-layout>
