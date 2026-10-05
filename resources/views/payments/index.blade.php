{{-- Pagamentos de reservas. Busca por sócio (nome ou CPF) no servidor, junto
     dos filtros de status e método. --}}
@php
    $field = 'h-11 rounded-full border border-line-strong bg-surface px-4 text-sm text-ink focus:border-grena focus:ring-4 focus:ring-grena-tint';
    $methods = ['credit_card' => 'Cartão de crédito', 'debit_card' => 'Cartão de débito', 'pix' => 'Pix'];
@endphp
<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title title="Pagamentos">
            Consulte na Rede e estorne pagamentos de reservas.
        </x-page-title>

        @include('partials.alerts')

        <x-search-bar name="member" :filters="['status_id', 'payment_method', 'date_from', 'date_to']" placeholder="Sócio: nome ou CPF" label="Buscar sócio">
            <x-slot:controls>
                <label for="filtro-status" class="sr-only">Status</label>
                <select id="filtro-status" name="status_id" class="{{ $field }} w-full sm:w-44">
                    <option value="">Todos os status</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status->id }}" @selected((string) request('status_id') === (string) $status->id)>{{ $status->portuguese ?? $status->name }}</option>
                    @endforeach
                </select>
                <label for="filtro-metodo" class="sr-only">Método</label>
                <select id="filtro-metodo" name="payment_method" class="{{ $field }} w-full sm:w-48">
                    <option value="">Todos os métodos</option>
                    @foreach($methods as $value => $label)
                        <option value="{{ $value }}" @selected(request('payment_method') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </x-slot:controls>
        </x-search-bar>

        <!-- TABELA -->
        @if($payments->isEmpty())
            <x-empty-state icon="card">
                Nenhum pagamento encontrado com esses filtros.
                <a href="{{ route('payment.index') }}" class="font-bold text-grena-ink hover:underline">Limpar a busca</a>.
            </x-empty-state>
        @else
        <div class="overflow-hidden rounded-card bg-surface shadow-card">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-line bg-subtle">
                                <th class="px-5 py-3.5 text-left text-[11px] font-bold text-ink-3 uppercase tracking-[0.08em]">#</th>
                                <th class="px-5 py-3.5 text-left text-[11px] font-bold text-ink-3 uppercase tracking-[0.08em]">Sócio</th>
                                <th class="px-5 py-3.5 text-left text-[11px] font-bold text-ink-3 uppercase tracking-[0.08em]">Método</th>
                                <th class="px-5 py-3.5 text-left text-[11px] font-bold text-ink-3 uppercase tracking-[0.08em]">Pago em</th>
                                <th class="px-5 py-3.5 text-center text-[11px] font-bold text-ink-3 uppercase tracking-[0.08em]">Status</th>
                                <th class="px-5 py-3.5 text-right text-[11px] font-bold text-ink-3 uppercase tracking-[0.08em]">Valor Pago</th>
                                <th class="px-5 py-3.5 text-right text-[11px] font-bold text-ink-3 uppercase tracking-[0.08em]">Estornado</th>
                                <th class="px-5 py-3.5"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach($payments as $payment)
                                @php
                                    $firstSchedule = $payment->schedules->first();
                                    $member = optional($firstSchedule)->member;
                                @endphp
                                <tr class="hover:bg-subtle transition">
                                    <td class="px-5 py-3.5 font-mono text-xs text-ink-3">#{{ $payment->id }}</td>
                                    <td class="px-5 py-3.5">
                                        <p class="font-semibold text-ink">{{ $member->name ?? 'Não identificado' }}</p>
                                        @if($payment->schedules->count() > 1)
                                            <p class="text-xs text-ink-3">{{ $payment->schedules->count() }} agendamentos vinculados</p>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3.5 text-ink-2">{{ $methods[$payment->payment_method] ?? $payment->payment_method }}</td>
                                    <td class="px-5 py-3.5 whitespace-nowrap font-mono text-xs text-ink-2">
                                        {{ $payment->paid_at ? \Carbon\Carbon::parse($payment->paid_at)->format('d/m/Y H:i') : '—' }}
                                    </td>
                                    <td class="px-5 py-3.5 text-center">
                                        @php
                                            $statusLabel = $payment->status->portuguese ?? $payment->status->name ?? '?';
                                        @endphp
                                        <x-pill :kind="(int) $payment->status_id === 0 ? 'danger' : 'ok'">{{ $statusLabel }}</x-pill>
                                    </td>
                                    <td class="px-5 py-3.5 text-right font-mono font-semibold text-ink">
                                        R$ {{ number_format($payment->paid_amount, 2, ',', '.') }}
                                    </td>
                                    <td class="px-5 py-3.5 text-right">
                                        @if($payment->refunded_amount > 0)
                                            <span class="font-mono font-semibold text-danger">R$ {{ number_format($payment->refunded_amount, 2, ',', '.') }}</span>
                                        @else
                                            <span class="text-ink-3">—</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3.5 text-right">
                                        <x-secondary-button-a size="sm" href="{{ route('payment.show', $payment->id) }}">Detalhes</x-secondary-button-a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

        </div>

        @if($payments->hasPages())
            {{ $payments->links() }}
        @endif
        @endif
    </x-page>

</x-app-layout>
