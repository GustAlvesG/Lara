<x-app-layout>

    <div class="max-w-full mx-auto py-8 px-4">

        <!-- Header -->
        <div class="mb-8 flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <a href="{{ route('company.access.monitor') }}" class="p-2 bg-white dark:bg-gray-800 rounded-xl shadow-md text-gray-400 dark:text-gray-500 hover:text-indigo-600 dark:hover:text-indigo-400 border border-gray-100 dark:border-gray-700 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                </a>
                <div>
                    <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white">Liberação Pontual</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Uma entrada, no dia, sem vínculo com empresa. Para o caso extraordinário.</p>
                </div>
            </div>
            <a href="{{ route('company.one-off.create') }}"
               class="px-4 py-2 bg-indigo-600 text-white rounded-lg font-bold text-sm shadow-md hover:bg-indigo-700 transition">
                Nova Liberação
            </a>
        </div>

        @include('partials.alerts')

        <!-- Stats do dia -->
        <div class="grid grid-cols-3 gap-4 mb-6">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
                <p class="text-2xl font-black text-gray-900 dark:text-white">{{ $stats['total'] }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">Liberações no dia</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
                <p class="text-2xl font-black text-amber-600 dark:text-amber-400">{{ $stats['available'] }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">Aguardando entrada</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
                <p class="text-2xl font-black text-green-700 dark:text-green-400">{{ $stats['used'] }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">Utilizadas</p>
            </div>
        </div>

        <!-- Filtro -->
        <form method="GET" action="{{ route('company.one-off.index') }}"
              class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-5 mb-6 flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-1.5">Dia</label>
                <input type="date" name="date" value="{{ $date->toDateString() }}"
                       class="px-3 py-2.5 border border-gray-200 dark:border-gray-600 rounded-xl text-sm outline-none focus:ring-2 focus:ring-indigo-400 bg-white dark:bg-gray-700 text-gray-900 dark:text-white dark:[color-scheme:dark]">
            </div>
            <button type="submit" class="px-4 py-2.5 bg-indigo-600 text-white rounded-xl font-bold text-sm hover:bg-indigo-700 transition shadow-sm">
                Filtrar
            </button>
            @unless($date->isToday())
                <a href="{{ route('company.one-off.index') }}" class="px-4 py-2.5 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-300 rounded-xl font-bold text-sm hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                    Hoje
                </a>
            @endunless
        </form>

        <!-- Tabela -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
            @if($accesses->isEmpty())
                <div class="py-16 text-center">
                    <p class="text-gray-400 dark:text-gray-500 font-medium">Nenhuma liberação pontual em {{ $date->format('d/m/Y') }}.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                <table class="w-full min-w-[900px] text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 dark:border-gray-700 bg-gray-50/70 dark:bg-gray-700/50">
                            <th class="px-5 py-3.5 text-left text-[11px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-wider">Criada</th>
                            <th class="px-5 py-3.5 text-left text-[11px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-wider">Pessoa</th>
                            <th class="px-5 py-3.5 text-left text-[11px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-wider">Motivo</th>
                            <th class="px-5 py-3.5 text-left text-[11px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-wider">Autorizada por</th>
                            <th class="px-5 py-3.5 text-center text-[11px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-5 py-3.5"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50 dark:divide-gray-700">
                        @foreach($accesses as $access)
                            @php
                                $status = $access->status();
                                $badge = [
                                    'available' => 'bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-400',
                                    'used'      => 'bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-400',
                                    'canceled'  => 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400',
                                    'expired'   => 'bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-400',
                                ][$status];
                            @endphp
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-700/30 transition align-top">
                                <td class="px-5 py-3.5 whitespace-nowrap font-semibold text-gray-800 dark:text-gray-200">
                                    {{ $access->created_at->format('H:i') }}
                                </td>
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center gap-3">
                                        @if($access->imageUrl())
                                            <img src="{{ $access->imageUrl() }}" class="w-9 h-9 rounded-full object-cover shrink-0" alt="">
                                        @else
                                            <div class="w-9 h-9 rounded-full bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-400 flex items-center justify-center text-sm font-black shrink-0">
                                                {{ mb_strtoupper(mb_substr($access->name, 0, 1)) }}
                                            </div>
                                        @endif
                                        <div>
                                            <p class="font-semibold text-gray-800 dark:text-gray-200">{{ $access->name }}</p>
                                            <p class="font-mono text-xs text-gray-400 dark:text-gray-500">{{ $access->formattedCpf() }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-3.5 text-gray-600 dark:text-gray-300 max-w-md whitespace-pre-line">{{ $access->reason }}</td>
                                <td class="px-5 py-3.5 text-gray-600 dark:text-gray-300">{{ $access->creator?->name ?? '—' }}</td>
                                <td class="px-5 py-3.5 text-center">
                                    <span class="inline-block px-2.5 py-1 rounded-full text-[11px] font-black uppercase {{ $badge }}">{{ $access->statusLabel() }}</span>
                                    @if($access->used_at)
                                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">entrou às {{ $access->used_at->format('H:i') }}</p>
                                    @elseif($access->canceled_at)
                                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">às {{ $access->canceled_at->format('H:i') }}{{ $access->canceler ? ' por ' . $access->canceler->name : '' }}</p>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-right">
                                    @if($status === 'available')
                                        <form method="POST" action="{{ route('company.one-off.cancel', $access) }}"
                                              onsubmit="return confirm('Cancelar a liberação de {{ addslashes($access->name) }}? A portaria deixa de encontrá-la.')">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="px-3 py-1.5 bg-white dark:bg-gray-700 border border-red-200 dark:border-red-800 text-red-600 dark:text-red-400 rounded-lg text-xs font-bold hover:bg-red-50 dark:hover:bg-red-900/30 transition">
                                                Cancelar
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
            @endif
        </div>

    </div>

</x-app-layout>
