<x-app-layout>

    <div class="max-w-7xl mx-auto pt-4 pb-10 px-4">

        <!-- HEADER -->
        <div class="mb-8 flex items-center justify-between flex-wrap gap-4">
            <div class="flex items-center gap-4">
                <a href="{{ route('dashboard') }}" class="p-2 bg-white rounded-xl shadow-md text-gray-400 hover:text-indigo-600 border border-gray-100 transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                </a>
                <div>
                    <h1 class="text-3xl font-extrabold text-gray-900 leading-tight">Gestão de Pagamentos</h1>
                    <p class="text-gray-500 font-medium">Visualize, consulte na Rede e estorne pagamentos de reservas.</p>
                </div>
            </div>
        </div>

        @if(session('success'))
            <div class="mb-6 p-4 rounded-2xl bg-green-50 border border-green-100 text-green-700 text-sm font-medium">
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="mb-6 p-4 rounded-2xl bg-red-50 border border-red-100 text-red-700 text-sm font-medium">
                {{ session('error') }}
            </div>
        @endif

        <!-- FILTROS -->
        <form method="GET" action="{{ route('payment.index') }}"
              class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 mb-6">
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4 items-end">

                <div class="col-span-2">
                    <label class="block text-xs font-black text-gray-400 uppercase tracking-wider mb-1.5">Sócio (nome ou CPF)</label>
                    <input type="text" name="member" value="{{ request('member') }}" placeholder="Buscar sócio..."
                           class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm outline-none focus:ring-2 focus:ring-indigo-400">
                </div>

                <div>
                    <label class="block text-xs font-black text-gray-400 uppercase tracking-wider mb-1.5">Status</label>
                    <select name="status_id" class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm outline-none focus:ring-2 focus:ring-indigo-400">
                        <option value="">Todos</option>
                        @foreach($statuses as $status)
                            <option value="{{ $status->id }}" {{ (string) request('status_id') === (string) $status->id ? 'selected' : '' }}>
                                {{ $status->portuguese ?? $status->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-black text-gray-400 uppercase tracking-wider mb-1.5">Método</label>
                    <select name="payment_method" class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm outline-none focus:ring-2 focus:ring-indigo-400">
                        <option value="">Todos</option>
                        @foreach(['credit_card' => 'Cartão de Crédito', 'debit_card' => 'Cartão de Débito', 'pix' => 'Pix'] as $value => $label)
                            <option value="{{ $value }}" {{ request('payment_method') === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex gap-2">
                    <button type="submit" class="flex-1 px-4 py-2.5 bg-indigo-600 text-white rounded-xl font-bold text-sm hover:bg-indigo-700 transition shadow-sm">
                        Filtrar
                    </button>
                    @if(request()->hasAny(['member','status_id','payment_method','date_from','date_to']))
                        <a href="{{ route('payment.index') }}" class="px-3 py-2.5 bg-white border border-gray-200 text-gray-500 rounded-xl font-bold text-sm hover:bg-gray-50 transition shrink-0">
                            ✕
                        </a>
                    @endif
                </div>

            </div>
        </form>

        <!-- TABELA -->
        <div class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">

            @if($payments->isEmpty())
                <div class="py-16 text-center">
                    <svg class="w-12 h-12 text-gray-200 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                    </svg>
                    <p class="text-gray-400 font-medium">Nenhum pagamento encontrado com esses filtros.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50/70">
                                <th class="px-5 py-3.5 text-left text-[11px] font-black text-gray-400 uppercase tracking-wider">#</th>
                                <th class="px-5 py-3.5 text-left text-[11px] font-black text-gray-400 uppercase tracking-wider">Sócio</th>
                                <th class="px-5 py-3.5 text-left text-[11px] font-black text-gray-400 uppercase tracking-wider">Método</th>
                                <th class="px-5 py-3.5 text-left text-[11px] font-black text-gray-400 uppercase tracking-wider">Pago em</th>
                                <th class="px-5 py-3.5 text-center text-[11px] font-black text-gray-400 uppercase tracking-wider">Status</th>
                                <th class="px-5 py-3.5 text-right text-[11px] font-black text-gray-400 uppercase tracking-wider">Valor Pago</th>
                                <th class="px-5 py-3.5 text-right text-[11px] font-black text-gray-400 uppercase tracking-wider">Estornado</th>
                                <th class="px-5 py-3.5"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach($payments as $payment)
                                @php
                                    $firstSchedule = $payment->schedules->first();
                                    $member = optional($firstSchedule)->member;
                                @endphp
                                <tr class="hover:bg-gray-50/50 transition">
                                    <td class="px-5 py-3.5 font-mono text-xs text-gray-400">#{{ $payment->id }}</td>
                                    <td class="px-5 py-3.5">
                                        <p class="font-semibold text-gray-800">{{ $member->name ?? 'Não identificado' }}</p>
                                        @if($payment->schedules->count() > 1)
                                            <p class="text-xs text-gray-400">{{ $payment->schedules->count() }} agendamentos vinculados</p>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3.5 text-gray-600">{{ $payment->payment_method }}</td>
                                    <td class="px-5 py-3.5 whitespace-nowrap text-gray-600">
                                        {{ $payment->paid_at ? \Carbon\Carbon::parse($payment->paid_at)->format('d/m/Y H:i') : '—' }}
                                    </td>
                                    <td class="px-5 py-3.5 text-center">
                                        @php
                                            $statusLabel = $payment->status->portuguese ?? $payment->status->name ?? '?';
                                            $statusClass = (int) $payment->status_id === 0 ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700';
                                        @endphp
                                        <span class="inline-block px-2.5 py-1 rounded-full text-[11px] font-black uppercase {{ $statusClass }}">
                                            {{ $statusLabel }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3.5 text-right font-semibold text-gray-700">
                                        R$ {{ number_format($payment->paid_amount, 2, ',', '.') }}
                                    </td>
                                    <td class="px-5 py-3.5 text-right">
                                        @if($payment->refunded_amount > 0)
                                            <span class="font-semibold text-red-600">R$ {{ number_format($payment->refunded_amount, 2, ',', '.') }}</span>
                                        @else
                                            <span class="text-gray-300">—</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3.5 text-right">
                                        <a href="{{ route('payment.show', $payment->id) }}"
                                           class="inline-flex items-center gap-1 px-3 py-1.5 bg-gray-900 text-white rounded-lg text-xs font-bold hover:bg-indigo-600 transition">
                                            Ver detalhes
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($payments->hasPages())
                    <div class="px-5 py-4 border-t border-gray-100">
                        {{ $payments->links() }}
                    </div>
                @endif
            @endif
        </div>

    </div>

</x-app-layout>
