<x-app-layout>

    <div class="max-w-6xl mx-auto pt-4 pb-10 px-4">

        <!-- HEADER -->
        <div class="mb-8 flex items-center justify-between flex-wrap gap-4">
            <div class="flex items-center gap-4">
                <a href="{{ route('payment.index') }}" class="p-2 bg-white rounded-xl shadow-md text-gray-400 hover:text-indigo-600 border border-gray-100 transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                </a>
                <div>
                    <h1 class="text-3xl font-extrabold text-gray-900 leading-tight">Pagamento #{{ $schedulePayment->id }}</h1>
                    <p class="text-gray-500 font-medium">Detalhes do pagamento e da(s) reserva(s) vinculada(s).</p>
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
        @error('amount')
            <div class="mb-6 p-4 rounded-2xl bg-red-50 border border-red-100 text-red-700 text-sm font-medium">{{ $message }}</div>
        @enderror

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">

            <!-- COLUNA ESQUERDA: DADOS DO PAGAMENTO -->
            <div class="lg:col-span-5 space-y-6">
                <div class="bg-white rounded-3xl shadow-2xl border border-gray-100 overflow-hidden">
                    @php
                        $statusLabel = $schedulePayment->status->portuguese ?? $schedulePayment->status->name ?? 'Status Desconhecido';
                        $isRefundedFull = (int) $schedulePayment->status_id === 0;
                        $remaining = round((float) $schedulePayment->paid_amount - (float) ($schedulePayment->refunded_amount ?? 0), 2);
                    @endphp
                    <div class="{{ $isRefundedFull ? 'bg-red-600' : 'bg-green-600' }} p-8 text-white text-center relative overflow-hidden">
                        <div class="relative z-10">
                            <p class="text-[10px] font-black uppercase tracking-widest opacity-80 mb-1">Valor Pago</p>
                            <h2 class="text-4xl font-black">R$ {{ number_format($schedulePayment->paid_amount, 2, ',', '.') }}</h2>
                            <span class="inline-block mt-3 px-4 py-1 bg-black/20 rounded-full text-[10px] font-black uppercase tracking-widest border border-white/20">
                                {{ $statusLabel }}
                            </span>
                        </div>
                    </div>

                    <div class="p-8 space-y-5">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="p-4 bg-gray-50 rounded-2xl border border-gray-100">
                                <p class="text-[10px] font-black text-gray-400 uppercase mb-1">Método</p>
                                <p class="text-sm font-bold text-gray-800">{{ $schedulePayment->payment_method }}</p>
                            </div>
                            <div class="p-4 bg-gray-50 rounded-2xl border border-gray-100">
                                <p class="text-[10px] font-black text-gray-400 uppercase mb-1">Pago em</p>
                                <p class="text-sm font-bold text-gray-800">
                                    {{ $schedulePayment->paid_at ? \Carbon\Carbon::parse($schedulePayment->paid_at)->format('d/m/Y H:i') : '—' }}
                                </p>
                            </div>
                            <div class="p-4 bg-gray-50 rounded-2xl border border-gray-100 col-span-2">
                                <p class="text-[10px] font-black text-gray-400 uppercase mb-1">ID da Transação (Rede)</p>
                                <p class="text-sm font-bold text-gray-800 font-mono break-all">{{ $schedulePayment->payment_integration_id ?? '—' }}</p>
                            </div>
                        </div>

                        @if($schedulePayment->refunded_amount > 0)
                            <div class="p-4 bg-red-50 rounded-2xl border border-red-100">
                                <p class="text-[10px] font-black text-red-400 uppercase mb-1">Total Estornado</p>
                                <p class="text-sm font-bold text-red-700">R$ {{ number_format($schedulePayment->refunded_amount, 2, ',', '.') }}</p>
                                @if($schedulePayment->refunded_at)
                                    <p class="text-[10px] text-red-400 mt-1">Em: {{ \Carbon\Carbon::parse($schedulePayment->refunded_at)->format('d/m/Y H:i') }}</p>
                                @endif
                                @if($schedulePayment->refunder)
                                    <p class="text-[10px] text-red-400">Por: {{ $schedulePayment->refunder->name }}</p>
                                @endif
                            </div>
                        @endif

                        <!-- Consulta ao vivo na Rede -->
                        <div class="pt-2 border-t border-gray-100">
                            <a href="{{ route('payment.show', ['schedulePayment' => $schedulePayment->id, 'consultar_rede' => 1]) }}"
                               class="w-full inline-flex items-center justify-center gap-2 py-3 bg-gray-50 border border-gray-200 rounded-xl font-bold text-xs uppercase tracking-wide text-gray-600 hover:bg-gray-100 transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 10a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                Consultar transação na Rede
                            </a>

                            @if($redeError)
                                <div class="mt-3 p-3 rounded-xl bg-red-50 border border-red-100 text-red-600 text-xs font-medium">
                                    {{ $redeError }}
                                </div>
                            @endif

                            @if($redeSummary)
                                <div class="mt-3 p-4 rounded-xl bg-gray-50 border border-gray-100 grid grid-cols-2 gap-3">
                                    <div>
                                        <p class="text-[10px] font-black text-gray-400 uppercase mb-0.5">Portador</p>
                                        <p class="text-sm font-bold text-gray-800">{{ $redeSummary['cardHolderName'] ?? '—' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-black text-gray-400 uppercase mb-0.5">Cartão</p>
                                        <p class="text-sm font-bold text-gray-800">•••• {{ $redeSummary['last4'] ?? '—' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-black text-gray-400 uppercase mb-0.5">Status na Rede</p>
                                        @php
                                            $redeStatusClass = $redeSummary['status'] === 'Approved' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700';
                                        @endphp
                                        <span class="inline-block px-2 py-0.5 rounded-full text-[11px] font-black uppercase {{ $redeStatusClass }}">
                                            {{ $redeSummary['status'] ?? '—' }}
                                        </span>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-black text-gray-400 uppercase mb-0.5">Modalidade</p>
                                        <p class="text-sm font-bold text-gray-800">{{ $redeSummary['kind'] ?? '—' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-black text-gray-400 uppercase mb-0.5">NSU</p>
                                        <p class="text-sm font-bold text-gray-800 font-mono">{{ $redeSummary['nsu'] ?? '—' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-black text-gray-400 uppercase mb-0.5">TID</p>
                                        <p class="text-sm font-bold text-gray-800 font-mono break-all">{{ $redeSummary['tid'] ?? '—' }}</p>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <!-- Estorno -->
                        @can('manage payments')
                            @if($remaining > 0 && $schedulePayment->payment_integration_id)
                                <form method="POST" action="{{ route('payment.refund', $schedulePayment->id) }}"
                                      class="pt-4 border-t border-gray-100 space-y-3"
                                      onsubmit="return confirm('Confirma o estorno deste pagamento na Rede? Essa ação não pode ser desfeita.');">
                                    @csrf
                                    <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Estornar Pagamento</p>
                                    <div class="flex gap-2">
                                        <div class="flex-1">
                                            <label class="block text-[10px] text-gray-400 mb-1">Valor (disponível: R$ {{ number_format($remaining, 2, ',', '.') }})</label>
                                            <input type="number" name="amount" step="0.01" min="0.01" max="{{ $remaining }}"
                                                   placeholder="{{ number_format($remaining, 2, '.', '') }}"
                                                   class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm outline-none focus:ring-2 focus:ring-red-400">
                                        </div>
                                        <div class="self-end">
                                            <button type="submit" class="px-4 py-2.5 bg-red-600 text-white rounded-xl font-bold text-sm hover:bg-red-700 transition shadow-sm">
                                                Estornar
                                            </button>
                                        </div>
                                    </div>
                                    <p class="text-[10px] text-gray-400">Deixe o valor em branco para estornar o valor total disponível.</p>
                                </form>
                            @elseif($isRefundedFull)
                                <div class="pt-4 border-t border-gray-100 text-center text-xs font-bold text-gray-400 uppercase">
                                    Pagamento já totalmente estornado
                                </div>
                            @endif
                        @endcan
                    </div>
                </div>
            </div>

            <!-- COLUNA DIREITA: AGENDAMENTOS VINCULADOS -->
            <div class="lg:col-span-7">
                <div class="bg-white rounded-3xl shadow-xl border border-gray-100 p-8">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="p-2 bg-indigo-600 rounded-lg text-white">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        </div>
                        <h3 class="text-xl font-extrabold text-gray-800">Agendamentos Vinculados</h3>
                    </div>

                    <div class="space-y-3">
                        @forelse($schedulePayment->schedules as $schedule)
                            @php
                                $statusConfig = match((int) $schedule->status_id) {
                                    1 => ['bg' => 'bg-green-100 text-green-700', 'text' => 'Confirmada'],
                                    3 => ['bg' => 'bg-amber-100 text-amber-700', 'text' => 'Pendente'],
                                    0 => ['bg' => 'bg-red-100 text-red-700', 'text' => 'Cancelada'],
                                    4 => ['bg' => 'bg-gray-200 text-gray-600', 'text' => 'Expirada'],
                                    10 => ['bg' => 'bg-gray-200 text-gray-600', 'text' => 'Antiga'],
                                    default => ['bg' => 'bg-gray-100 text-gray-500', 'text' => '?'],
                                };
                            @endphp
                            <a href="{{ route('schedule.show', $schedule->id) }}"
                               class="block relative flex items-center p-4 border-2 border-gray-100 rounded-2xl hover:border-indigo-200 hover:bg-indigo-50/40 transition">
                                <div class="flex-grow">
                                    <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">
                                        {{ optional($schedule->member)->name ?? 'Sócio não identificado' }}
                                    </p>
                                    <p class="text-lg font-black text-gray-800 leading-none">
                                        #{{ $schedule->id }} — {{ \Carbon\Carbon::parse($schedule->start_schedule)->format('d/m/Y H:i') }}
                                    </p>
                                    <p class="text-xs font-bold text-gray-500 mt-1">
                                        {{ optional($schedule->place)->name ?? 'Local removido' }}
                                        @if($schedule->place && $schedule->place->group)
                                            — {{ $schedule->place->group->name }}
                                        @endif
                                    </p>
                                </div>
                                <div class="ml-auto text-right">
                                    <span class="px-2 py-0.5 rounded text-[8px] font-black uppercase {{ $statusConfig['bg'] }}">
                                        {{ $statusConfig['text'] }}
                                    </span>
                                    @if($schedule->cancel_reason)
                                        <p class="text-[10px] text-gray-400 mt-1 italic max-w-[180px]">"{{ $schedule->cancel_reason }}"</p>
                                    @endif
                                </div>
                            </a>
                        @empty
                            <p class="text-sm text-gray-400 italic text-center py-8">Nenhum agendamento vinculado a este pagamento.</p>
                        @endforelse
                    </div>
                </div>
            </div>

        </div>

    </div>

</x-app-layout>
