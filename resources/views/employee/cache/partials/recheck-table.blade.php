<div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
    <div class="p-6 border-b border-gray-50 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-700/50">
        <h2 class="text-lg font-bold text-gray-800 dark:text-white">{{ $title }}</h2>
    </div>

    @if($caches->isEmpty())
        <div class="p-8 text-center">
            <p class="text-gray-500 dark:text-gray-400">{{ $empty }}</p>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider bg-gray-50 dark:bg-gray-900/40">
                    <tr>
                        <th class="px-6 py-3">Funcionário</th>
                        <th class="px-6 py-3">Evento / Data</th>
                        <th class="px-6 py-3">Previsto</th>
                        <th class="px-6 py-3">Real (assinado)</th>
                        <th class="px-6 py-3">Valor</th>
                        <th class="px-6 py-3 text-right">Decisão</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach($caches as $cache)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                        <td class="px-6 py-4">
                            <div class="font-semibold text-gray-900 dark:text-white">{{ $cache->employee->name }}</div>
                            <div class="text-xs text-gray-400">{{ $cache->employee->employee_code }} · {{ $cache->functionFreelancer->name }}</div>
                        </td>
                        <td class="px-6 py-4 text-gray-700 dark:text-gray-300">
                            {{ $cache->location }}
                            <span class="block text-xs text-gray-400">{{ $cache->event_date->format('d/m/Y') }}</span>
                        </td>
                        <td class="px-6 py-4 text-gray-500 dark:text-gray-400 whitespace-nowrap">
                            {{ $cache->formattedExpectedPeriod() }}
                            <span class="block text-xs">faixa de {{ $cache->expected_hours }}h · R$ {{ number_format($cache->expected_price, 2, ',', '.') }}</span>
                        </td>
                        <td class="px-6 py-4 text-amber-700 dark:text-amber-300 font-semibold whitespace-nowrap">
                            {{ $cache->formattedActualPeriod() }}
                            <span class="block text-xs font-normal">
                                faixa de {{ $cache->hours }}h · assinado em {{ $cache->employee_signed_at?->format('d/m/Y H:i') }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="font-bold text-gray-900 dark:text-white">R$ {{ number_format($cache->price, 2, ',', '.') }}</span>
                            @php $diff = $cache->priceDifference(); @endphp
                            @if(abs($diff) >= 0.01)
                                <span class="block text-xs font-bold {{ $diff > 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                                    {{ $diff > 0 ? '+' : '−' }} R$ {{ number_format(abs($diff), 2, ',', '.') }}
                                </span>
                            @else
                                <span class="block text-xs text-gray-400">mesmo valor</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <form method="POST" action="{{ route('employee-caches.recheck', $cache) }}"
                                  x-data="{ rejecting: false }" class="flex flex-col items-end gap-2">
                                @csrf
                                <input type="hidden" name="stage" value="{{ $stage }}">

                                <div class="flex gap-2" x-show="!rejecting">
                                    <button type="submit" name="decision" value="approve"
                                        class="px-4 py-2 bg-green-600 text-white rounded-lg text-xs font-bold hover:bg-green-700 transition">
                                        Confirmar
                                    </button>
                                    <button type="button" @click="rejecting = true"
                                        class="px-4 py-2 text-red-700 dark:text-red-400 rounded-lg text-xs font-bold hover:bg-red-50 dark:hover:bg-red-900/20 transition">
                                        Recusar
                                    </button>
                                </div>

                                <div x-show="rejecting" x-cloak class="flex flex-col items-end gap-2 min-w-64">
                                    <input type="text" name="reason" placeholder="Motivo da recusa"
                                        class="w-full px-2 py-1 text-xs border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
                                    <div class="flex gap-2">
                                        <button type="button" @click="rejecting = false"
                                            class="px-3 py-1 text-xs font-bold text-gray-500 hover:underline">Voltar</button>
                                        <button type="submit" name="decision" value="reject"
                                            class="px-4 py-2 bg-red-600 text-white rounded-lg text-xs font-bold hover:bg-red-700 transition">
                                            Confirmar recusa
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
