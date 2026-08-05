<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Solicitação de Cachê #') . $batch->id }}
        </h2>
    </x-slot>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        @include('employee.cache.partials.tabs', ['activeTab' => $isManager && !$isOwner ? 'employee-caches.queue' : 'employee-caches.batches'])
        @include('partials.alerts')

        <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white leading-tight">
                    {{ $batch->title ?? 'Solicitação #' . $batch->id }}
                </h1>
                <p class="text-gray-500 dark:text-gray-400 font-medium">
                    {{ $batch->statusLabel() }}
                    · {{ $batch->caches->count() }} cachê(s)
                    · Total previsto R$ {{ number_format($batch->caches->sum('expected_price'), 2, ',', '.') }}
                </p>
                <p class="text-xs text-gray-400 mt-1">
                    Solicitado por {{ $batch->createdBy?->name ?? '—' }}
                    @if($batch->sector) · setor {{ $batch->sector->name }} @endif
                    @if($batch->sent_at) · enviado em {{ $batch->sent_at->format('d/m/Y H:i') }} @endif
                    @if($batch->reviewedBy) · analisado por {{ $batch->reviewedBy->name }} em {{ $batch->reviewed_at?->format('d/m/Y H:i') }} @endif
                </p>
            </div>

            @if($isOwner && $batch->isDraft())
                <div class="flex gap-3">
                    <form method="POST" action="{{ route('employee-caches.batches.discard', $batch) }}"
                          onsubmit="return confirm('Descartar esta solicitação? As linhas serão apagadas.')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="px-4 py-3 rounded-xl font-bold text-red-700 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition">Descartar</button>
                    </form>
                    <form method="POST" action="{{ route('employee-caches.batches.send', $batch) }}">
                        @csrf
                        <button type="submit" class="px-6 py-3 bg-[#A00001] text-white rounded-xl font-bold shadow-lg hover:bg-[#800000] transition">
                            Enviar para a gerência
                        </button>
                    </form>
                </div>
            @endif
        </div>

        <form method="POST" action="{{ route('employee-caches.batches.review', $batch) }}">
            @csrf

            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                @if($canReview)
                    <div class="p-6 border-b border-gray-50 dark:border-gray-700 bg-amber-50 dark:bg-amber-900/20">
                        <p class="font-bold text-amber-800 dark:text-amber-200">Análise da gerência</p>
                        <p class="text-sm text-amber-700 dark:text-amber-300">
                            Tudo começa marcado como aprovar. Recusar exige o motivo, que volta para o coordenador.
                            Os aprovados ficam disponíveis para o funcionário assinar.
                        </p>
                    </div>
                @endif

                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider bg-gray-50 dark:bg-gray-900/40">
                            <tr>
                                <th class="px-6 py-3">Funcionário</th>
                                <th class="px-6 py-3">Função</th>
                                <th class="px-6 py-3">Evento / Local</th>
                                <th class="px-6 py-3">Data</th>
                                <th class="px-6 py-3">Previsto</th>
                                <th class="px-6 py-3">Valor</th>
                                <th class="px-6 py-3">{{ $canReview ? 'Decisão' : 'Situação' }}</th>
                                @if($isOwner && $batch->isDraft())<th class="px-6 py-3"></th>@endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach($batch->caches as $cache)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                                <td class="px-6 py-4">
                                    <div class="font-semibold text-gray-900 dark:text-white">{{ $cache->employee->name }}</div>
                                    <div class="text-xs text-gray-400">{{ $cache->employee->employee_code }} · {{ $cache->employee->department }}</div>
                                </td>
                                <td class="px-6 py-4 text-gray-700 dark:text-gray-300">{{ $cache->functionFreelancer->name }}</td>
                                <td class="px-6 py-4 text-gray-700 dark:text-gray-300">
                                    {{ $cache->location }}
                                    @if($cache->description)<span class="block text-xs text-gray-400">{{ $cache->description }}</span>@endif
                                </td>
                                <td class="px-6 py-4 text-gray-700 dark:text-gray-300 whitespace-nowrap">{{ $cache->event_date->format('d/m/Y') }}</td>
                                <td class="px-6 py-4 text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                    {{ $cache->formattedExpectedPeriod() }}
                                    <span class="block text-xs">faixa de {{ $cache->expected_hours }}h</span>
                                </td>
                                <td class="px-6 py-4 font-bold text-gray-900 dark:text-white whitespace-nowrap">
                                    R$ {{ number_format($cache->expected_price, 2, ',', '.') }}
                                </td>
                                <td class="px-6 py-4">
                                    @if($canReview)
                                        <div x-data="{ decision: 'approve' }" class="space-y-2 min-w-52">
                                            <div class="flex gap-3 text-xs font-bold">
                                                <label class="flex items-center gap-1 cursor-pointer">
                                                    <input type="radio" value="approve" x-model="decision" name="decisions[{{ $cache->id }}][decision]" checked>
                                                    <span class="text-green-700 dark:text-green-400">Aprovar</span>
                                                </label>
                                                <label class="flex items-center gap-1 cursor-pointer">
                                                    <input type="radio" value="reject" x-model="decision" name="decisions[{{ $cache->id }}][decision]">
                                                    <span class="text-red-700 dark:text-red-400">Recusar</span>
                                                </label>
                                            </div>
                                            <input type="text" name="decisions[{{ $cache->id }}][reason]" x-show="decision === 'reject'" x-cloak
                                                placeholder="Motivo da recusa"
                                                class="w-full px-2 py-1 text-xs border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
                                        </div>
                                    @else
                                        @include('employee.cache.partials.status-badge')
                                        @if($cache->isManagerRejected() && $cache->manager_rejection_reason)
                                            <span class="block text-xs text-red-600 dark:text-red-400 mt-1">{{ $cache->manager_rejection_reason }}</span>
                                        @endif
                                    @endif
                                </td>
                                @if($isOwner && $batch->isDraft())
                                    <td class="px-6 py-4 text-right">
                                        <form method="POST" action="{{ route('employee-caches.batches.items.remove', [$batch, $cache]) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 dark:text-red-400 hover:underline text-xs font-bold">Retirar</button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($canReview)
                    <div class="p-6 border-t border-gray-100 dark:border-gray-700 flex justify-end">
                        <button type="submit" class="px-6 py-3 bg-[#A00001] text-white rounded-xl font-bold shadow-lg hover:bg-[#800000] transition">
                            Concluir análise
                        </button>
                    </div>
                @endif
            </div>
        </form>
    </div>
</div>
</x-app-layout>
