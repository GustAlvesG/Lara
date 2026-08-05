<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Cachê de Funcionários') }}
        </h2>
    </x-slot>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        @include('employee.cache.partials.tabs')
        @include('partials.alerts')

        <div class="mb-6">
            <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white leading-tight">Cachês</h1>
            <p class="text-gray-500 dark:text-gray-400 font-medium">
                Tudo o que foi solicitado para os funcionários que você acompanha, em qualquer etapa do trâmite.
            </p>
        </div>

        <form method="GET" class="mb-6 bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700 p-4 grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="md:col-span-2">
                <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 mb-1">Busca</label>
                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="nome, matrícula, CPF ou evento/local"
                    class="w-full px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 mb-1">De</label>
                <input type="date" name="from" value="{{ $filters['from'] ?? '' }}"
                    class="w-full px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
            </div>
            <div class="flex gap-2 items-end">
                <div class="flex-1">
                    <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 mb-1">Até</label>
                    <input type="date" name="to" value="{{ $filters['to'] ?? '' }}"
                        class="w-full px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
                </div>
                <button type="submit" class="px-4 py-2 bg-[#A00001] text-white rounded-lg font-bold hover:bg-[#800000] transition">Filtrar</button>
            </div>
        </form>

        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
            @if($caches->isEmpty())
                <div class="p-12 text-center">
                    <p class="text-gray-500 dark:text-gray-400">Nenhum cachê encontrado.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider bg-gray-50 dark:bg-gray-900/40">
                            <tr>
                                <th class="px-6 py-3">Funcionário</th>
                                <th class="px-6 py-3">Função</th>
                                <th class="px-6 py-3">Evento / Local</th>
                                <th class="px-6 py-3">Data</th>
                                <th class="px-6 py-3">Previsto</th>
                                <th class="px-6 py-3">Real</th>
                                <th class="px-6 py-3">Valor</th>
                                <th class="px-6 py-3">Situação</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach($caches as $cache)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                                <td class="px-6 py-4">
                                    <div class="font-semibold text-gray-900 dark:text-white">{{ $cache->employee->name }}</div>
                                    <div class="text-xs text-gray-400">{{ $cache->employee->employee_code }} · {{ $cache->employee->department }}</div>
                                </td>
                                <td class="px-6 py-4 text-gray-700 dark:text-gray-300">{{ $cache->functionFreelancer->name }}</td>
                                <td class="px-6 py-4 text-gray-700 dark:text-gray-300">{{ $cache->location }}</td>
                                <td class="px-6 py-4 text-gray-700 dark:text-gray-300">{{ $cache->event_date->format('d/m/Y') }}</td>
                                <td class="px-6 py-4 text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                    {{ $cache->formattedExpectedPeriod() }}
                                    <span class="block text-xs">{{ $cache->expected_hours }}h · R$ {{ number_format($cache->expected_price, 2, ',', '.') }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap {{ $cache->hasDivergence() ? 'text-amber-600 dark:text-amber-400 font-semibold' : 'text-gray-500 dark:text-gray-400' }}">
                                    {{ $cache->formattedActualPeriod() ?? '—' }}
                                    @if($cache->hasDivergence())
                                        <span class="block text-xs">divergente</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 font-bold text-gray-900 dark:text-white whitespace-nowrap">
                                    R$ {{ number_format($cache->price ?? $cache->expected_price, 2, ',', '.') }}
                                </td>
                                <td class="px-6 py-4">@include('employee.cache.partials.status-badge')</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="p-4 border-t border-gray-100 dark:border-gray-700">
                    {{ $caches->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
</x-app-layout>
