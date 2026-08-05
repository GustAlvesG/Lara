<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Aprovação de Cachê') }}
        </h2>
    </x-slot>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        @include('employee.cache.partials.tabs')
        @include('partials.alerts')

        <div class="mb-6">
            <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white leading-tight">Aguardando a gerência</h1>
            <p class="text-gray-500 dark:text-gray-400 font-medium">
                Solicitações enviadas pelos coordenadores. A decisão é item a item, antes de o funcionário assinar.
            </p>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
            @if($batches->isEmpty())
                <div class="p-12 text-center">
                    <p class="text-gray-500 dark:text-gray-400">Nenhuma solicitação aguardando análise.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider bg-gray-50 dark:bg-gray-900/40">
                            <tr>
                                <th class="px-6 py-3">#</th>
                                <th class="px-6 py-3">Título</th>
                                <th class="px-6 py-3">Coordenador</th>
                                <th class="px-6 py-3">Setor</th>
                                <th class="px-6 py-3">Cachês</th>
                                <th class="px-6 py-3">Total previsto</th>
                                <th class="px-6 py-3">Enviada em</th>
                                <th class="px-6 py-3 text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach($batches as $batch)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                                <td class="px-6 py-4 font-bold text-gray-400">{{ $batch->id }}</td>
                                <td class="px-6 py-4 font-semibold text-gray-900 dark:text-white">{{ $batch->title ?? '—' }}</td>
                                <td class="px-6 py-4 text-gray-700 dark:text-gray-300">{{ $batch->createdBy?->name ?? '—' }}</td>
                                <td class="px-6 py-4 text-gray-700 dark:text-gray-300">{{ $batch->sector?->name ?? '—' }}</td>
                                <td class="px-6 py-4 text-gray-700 dark:text-gray-300">{{ $batch->caches_count }}</td>
                                <td class="px-6 py-4 font-bold text-gray-900 dark:text-white">R$ {{ number_format($batch->caches_sum_expected_price ?? 0, 2, ',', '.') }}</td>
                                <td class="px-6 py-4 text-gray-500 dark:text-gray-400">{{ $batch->sent_at?->format('d/m/Y H:i') }}</td>
                                <td class="px-6 py-4 text-right">
                                    <a href="{{ route('employee-caches.batches.show', $batch) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline font-medium text-xs">Analisar</a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
</x-app-layout>
