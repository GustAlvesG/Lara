{{-- Financeiro › Recebimentos: todo pagamento de Reservas, Torneios e
     Ingressos numa lista (FinanceLedger). Busca por pagador, CPF, referência
     ou TID; filtros de origem, forma, situação e período. O detalhe e o
     estorno ficam na tela de origem — o link leva até ela. --}}
@php
    use App\Services\Finance\FinanceLedger;

    $field = 'h-11 rounded-full border border-line-strong bg-surface px-4 text-sm text-ink focus:border-grena focus:ring-4 focus:ring-grena-tint';
    $money = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
    $th = 'px-5 py-3.5 text-left text-[11px] font-bold text-ink-3 uppercase tracking-[0.08em]';
    $thr = 'px-5 py-3.5 text-right text-[11px] font-bold text-ink-3 uppercase tracking-[0.08em]';
    $label = 'text-[11px] font-bold uppercase tracking-[0.08em] text-ink-3';
    $pill = [FinanceLedger::STATUS_PAID => 'ok', FinanceLedger::STATUS_PARTIAL => 'warn', FinanceLedger::STATUS_REFUNDED => 'danger'];
    $total = $summary['total'];
@endphp
<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title title="Recebimentos">
            Todo pagamento de Reservas, Torneios e Ingressos — pelo site (eRede) ou por baixa manual. O detalhe e o estorno abrem na tela de cada origem.
            <x-slot:actions>
                <x-secondary-button-a href="{{ route('finance.receipts.csv', $query) }}"><x-icon name="download" /> Exportar CSV</x-secondary-button-a>
            </x-slot:actions>
        </x-page-title>

        <x-search-bar name="busca" :filters="['origem', 'forma', 'situacao', 'de', 'ate']" placeholder="Pagador, CPF, evento/torneio/local ou TID" label="Buscar recebimento">
            <x-slot:controls>
                <label for="filtro-origem" class="sr-only">Origem</label>
                <select id="filtro-origem" name="origem" class="{{ $field }} w-full sm:w-40">
                    <option value="">Todas as origens</option>
                    @foreach (FinanceLedger::ORIGINS as $value => $text)
                        <option value="{{ $value }}" @selected($filters['origin'] === $value)>{{ $text }}</option>
                    @endforeach
                </select>
                <label for="filtro-forma" class="sr-only">Forma</label>
                <select id="filtro-forma" name="forma" class="{{ $field }} w-full sm:w-52">
                    <option value="">Todas as formas</option>
                    @foreach ($methods as $value => $text)
                        <option value="{{ $value }}" @selected($filters['method'] === $value)>{{ $text }}</option>
                    @endforeach
                </select>
                <label for="filtro-situacao" class="sr-only">Situação</label>
                <select id="filtro-situacao" name="situacao" class="{{ $field }} w-full sm:w-44">
                    <option value="">Toda situação</option>
                    @foreach (FinanceLedger::STATUSES as $value => $text)
                        <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $text }}</option>
                    @endforeach
                </select>
                <label for="filtro-de" class="sr-only">De</label>
                <input id="filtro-de" type="date" name="de" value="{{ $filters['from']->format('Y-m-d') }}" title="De" class="{{ $field }} w-full sm:w-44">
                <label for="filtro-ate" class="sr-only">Até</label>
                <input id="filtro-ate" type="date" name="ate" value="{{ $filters['to']->format('Y-m-d') }}" title="Até" class="{{ $field }} w-full sm:w-44">
            </x-slot:controls>
        </x-search-bar>

        <section class="grid grid-cols-2 gap-4 rounded-card bg-surface p-5 shadow-card sm:grid-cols-4">
            <div><p class="{{ $label }}">Recebido</p><p class="mt-1 font-mono text-xl font-bold text-ink">{{ $money($total['paid']) }}</p><p class="text-xs text-ink-3">{{ $total['count'] }} pagamento(s)</p></div>
            <div><p class="{{ $label }}">Estornado</p><p class="mt-1 font-mono text-xl text-danger">{{ $money($total['refunded']) }}</p></div>
            <div><p class="{{ $label }}">Líquido</p><p class="mt-1 font-mono text-xl font-bold text-ink">{{ $money($total['net']) }}</p></div>
            <div><p class="{{ $label }}">Site × balcão</p><p class="mt-1 font-mono text-sm text-ink">{{ $money($total['online']) }} · {{ $money($total['manual']) }}</p></div>
        </section>

        @if ($receipts->isEmpty())
            <x-empty-state icon="money">
                Nenhum recebimento com esses filtros.
                <a href="{{ route('finance.receipts.index') }}" class="font-bold text-grena-ink hover:underline">Limpar a busca</a>.
            </x-empty-state>
        @else
            <div class="overflow-hidden rounded-card bg-surface shadow-card">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-line bg-subtle">
                                <th class="{{ $th }}">Pago em</th>
                                <th class="{{ $th }}">Origem</th>
                                <th class="{{ $th }}">Pagador</th>
                                <th class="{{ $th }}">Referência</th>
                                <th class="{{ $th }}">Forma</th>
                                <th class="{{ $th }}">Situação</th>
                                <th class="{{ $thr }}">Valor pago</th>
                                <th class="{{ $thr }}">Estornado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($receipts as $row)
                                @php $status = FinanceLedger::status($row); @endphp
                                <tr class="hover:bg-subtle transition">
                                    <td class="px-5 py-3.5 whitespace-nowrap font-mono text-xs text-ink-2">{{ $row->paid_at ? \Illuminate\Support\Carbon::parse($row->paid_at)->format('d/m/Y H:i') : '—' }}</td>
                                    <td class="px-5 py-3.5 text-ink-2">{{ FinanceLedger::ORIGINS[$row->origin] }} <span class="block font-mono text-xs text-ink-3">#{{ $row->id }}</span></td>
                                    <td class="px-5 py-3.5"><p class="font-semibold text-ink">{{ $row->payer_name ?: 'Não identificado' }}</p></td>
                                    <td class="px-5 py-3.5">@include('finance.partials.origin-link', ['origin' => $row->origin, 'ref' => $row->reference_id, 'text' => $row->reference ?: 'Ver'])</td>
                                    <td class="px-5 py-3.5 text-ink-2">
                                        {{ \App\Services\Finance\PaymentMethods::label($row->payment_method) }}
                                        @if ($row->is_manual)<span class="block text-[11px] font-bold uppercase text-ink-3">Baixa manual{{ $row->recorded_by && isset($recorders[$row->recorded_by]) ? ' · ' . $recorders[$row->recorded_by] : '' }}</span>@endif
                                    </td>
                                    <td class="px-5 py-3.5"><x-pill :kind="$pill[$status]">{{ FinanceLedger::STATUSES[$status] }}</x-pill></td>
                                    <td class="px-5 py-3.5 text-right font-mono font-semibold text-ink">{{ $money($row->paid_amount) }}</td>
                                    <td class="px-5 py-3.5 text-right">@if ((float) $row->refunded_amount > 0)<span class="font-mono font-semibold text-danger">{{ $money($row->refunded_amount) }}</span>@else<span class="text-ink-3">—</span>@endif</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($receipts->hasPages())
                {{ $receipts->links() }}
            @endif
        @endif
    </x-page>
</x-app-layout>
