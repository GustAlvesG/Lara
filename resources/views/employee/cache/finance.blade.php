<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Financeiro do Cachê') }}
        </h2>
    </x-slot>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

        @include('employee.cache.partials.tabs')
        @include('partials.alerts')

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700 p-6">
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">A pagar</p>
                <p class="text-3xl font-extrabold text-gray-900 dark:text-white">R$ {{ number_format($pending->sum('price'), 2, ',', '.') }}</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $pending->count() }} cachê(s)</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700 p-6">
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Já pagos</p>
                <p class="text-3xl font-extrabold text-green-700 dark:text-green-400">R$ {{ number_format($paid->sum('price'), 2, ',', '.') }}</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $paid->count() }} cachê(s)</p>
            </div>
        </div>

        <div class="bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-100 dark:border-indigo-800 rounded-2xl p-4 text-sm text-indigo-800 dark:text-indigo-200">
            A baixa aqui é <strong>manual</strong>: o cachê não é pago pelo Pix automático. Marcar registra que o
            pagamento saiu por fora (folha ou caixa), com data e responsável.
        </div>

        <form method="POST" action="{{ route('employee-caches.pay') }}" x-data="{ selected: [] }">
            @csrf

            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="p-6 border-b border-gray-50 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-700/50">
                    <h2 class="text-lg font-bold text-gray-800 dark:text-white">Aguardando pagamento</h2>
                </div>

                @if($pending->isEmpty())
                    <div class="p-12 text-center">
                        <p class="text-gray-500 dark:text-gray-400">Nenhum cachê aguardando pagamento.</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider bg-gray-50 dark:bg-gray-900/40">
                                <tr>
                                    <th class="px-4 py-3"></th>
                                    <th class="px-6 py-3">Funcionário</th>
                                    <th class="px-6 py-3">Setor</th>
                                    <th class="px-6 py-3">Evento / Data</th>
                                    <th class="px-6 py-3">Horário assinado</th>
                                    <th class="px-6 py-3">Faixa</th>
                                    <th class="px-6 py-3">Valor</th>
                                    <th class="px-6 py-3 text-right">Ação</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach($pending as $cache)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                                    <td class="px-4 py-4">
                                        <input type="checkbox" name="caches[]" value="{{ $cache->id }}" x-model="selected"
                                            class="rounded border-gray-300 text-[#A00001] focus:ring-red-500">
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="font-semibold text-gray-900 dark:text-white">{{ $cache->employee->name }}</div>
                                        <div class="text-xs text-gray-400">{{ $cache->employee->employee_code }} · CPF {{ $cache->employee->cpf }}</div>
                                    </td>
                                    <td class="px-6 py-4 text-gray-700 dark:text-gray-300">{{ $cache->employee->department }}</td>
                                    <td class="px-6 py-4 text-gray-700 dark:text-gray-300">
                                        {{ $cache->location }}
                                        <span class="block text-xs text-gray-400">{{ $cache->event_date->format('d/m/Y') }}</span>
                                    </td>
                                    <td class="px-6 py-4 text-gray-700 dark:text-gray-300 whitespace-nowrap">
                                        {{ $cache->formattedActualPeriod() }}
                                        @if($cache->hasDivergence())
                                            <span class="block text-xs text-amber-600 dark:text-amber-400">reconferido (divergiu do previsto)</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-gray-700 dark:text-gray-300">{{ $cache->hours }}h</td>
                                    <td class="px-6 py-4 font-bold text-gray-900 dark:text-white whitespace-nowrap">R$ {{ number_format($cache->price, 2, ',', '.') }}</td>
                                    <td class="px-6 py-4 text-right">
                                        <button type="submit" name="only" value="{{ $cache->id }}"
                                            class="px-4 py-2 bg-green-600 text-white rounded-lg text-xs font-bold hover:bg-green-700 transition">
                                            Dar baixa
                                        </button>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="p-4 border-t border-gray-100 dark:border-gray-700 flex items-center justify-between" x-show="selected.length > 0" x-cloak>
                        <p class="text-sm font-bold text-gray-600 dark:text-gray-300">
                            <span x-text="selected.length"></span> selecionado(s)
                        </p>
                        <button type="submit"
                            onclick="return confirm('Dar baixa nos cachês selecionados?')"
                            class="px-6 py-3 bg-green-600 text-white rounded-xl font-bold shadow-lg hover:bg-green-700 transition">
                            Dar baixa nos selecionados
                        </button>
                    </div>
                @endif
            </div>
        </form>

        @if($paid->isNotEmpty())
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="p-6 border-b border-gray-50 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-700/50">
                    <h2 class="text-lg font-bold text-gray-800 dark:text-white">Pagos</h2>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider bg-gray-50 dark:bg-gray-900/40">
                            <tr>
                                <th class="px-6 py-3">Funcionário</th>
                                <th class="px-6 py-3">Evento / Data</th>
                                <th class="px-6 py-3">Valor</th>
                                <th class="px-6 py-3">Baixa</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach($paid as $cache)
                            <tr>
                                <td class="px-6 py-4 font-semibold text-gray-900 dark:text-white">{{ $cache->employee->name }}</td>
                                <td class="px-6 py-4 text-gray-700 dark:text-gray-300">
                                    {{ $cache->location }}
                                    <span class="block text-xs text-gray-400">{{ $cache->event_date->format('d/m/Y') }}</span>
                                </td>
                                <td class="px-6 py-4 font-bold text-gray-900 dark:text-white">R$ {{ number_format($cache->price, 2, ',', '.') }}</td>
                                <td class="px-6 py-4 text-gray-500 dark:text-gray-400 text-xs">
                                    {{ $cache->paid_at?->format('d/m/Y H:i') }}
                                    @if($cache->paidBy) · {{ $cache->paidBy->name }} @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>
</x-app-layout>
