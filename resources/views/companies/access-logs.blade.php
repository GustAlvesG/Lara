<x-app-layout>

    <div class="max-w-6xl mx-auto py-8 px-4">

        <!-- Header -->
        <div class="mb-8 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <a href="{{ route('company.access.monitor') }}" class="p-2 bg-white dark:bg-gray-800 rounded-xl shadow-md text-gray-400 dark:text-gray-500 hover:text-indigo-600 dark:hover:text-indigo-400 border border-gray-100 dark:border-gray-700 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                </a>
                <div>
                    <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white">Histórico de Acessos</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Registro de todas as validações realizadas.</p>
                </div>
            </div>
            <a href="{{ route('company.access.monitor') }}"
               class="px-4 py-2 bg-indigo-600 text-white rounded-lg font-bold text-sm shadow-md hover:bg-indigo-700 transition">
                Monitor de Acesso
            </a>
        </div>

        <!-- Stats do dia -->
        <div class="grid grid-cols-3 gap-4 mb-6">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-5 flex items-center gap-4">
                <div class="w-12 h-12 bg-indigo-100 dark:bg-indigo-900/40 rounded-xl flex items-center justify-center shrink-0">
                    <svg class="w-6 h-6 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                </div>
                <div>
                    <p class="text-2xl font-black text-gray-900 dark:text-white">{{ $stats['total'] }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">Registros hoje</p>
                </div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-5 flex items-center gap-4">
                <div class="w-12 h-12 bg-green-100 dark:bg-green-900/40 rounded-xl flex items-center justify-center shrink-0">
                    <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-2xl font-black text-green-700 dark:text-green-400">{{ $stats['allowed'] }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">Permitidos hoje</p>
                </div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-5 flex items-center gap-4">
                <div class="w-12 h-12 bg-red-100 dark:bg-red-900/40 rounded-xl flex items-center justify-center shrink-0">
                    <svg class="w-6 h-6 text-red-500 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-2xl font-black text-red-600 dark:text-red-400">{{ $stats['denied'] }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">Negados hoje</p>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <form method="GET" action="{{ route('company.access.logs') }}"
              class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-5 mb-6">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 items-end">

                <div>
                    <label class="block text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-1.5">Empresa</label>
                    <select name="company_id" class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-600 rounded-xl text-sm outline-none focus:ring-2 focus:ring-indigo-400 bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                        <option value="">Todas</option>
                        @foreach($companies as $company)
                            <option value="{{ $company->id }}" {{ request('company_id') == $company->id ? 'selected' : '' }}>
                                {{ $company->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-1.5">Status</label>
                    <select name="status" class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-600 rounded-xl text-sm outline-none focus:ring-2 focus:ring-indigo-400 bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                        <option value="">Todos</option>
                        <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>Permitido</option>
                        <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>Negado</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-1.5">De</label>
                    <input type="date" name="date_from" value="{{ request('date_from') }}"
                           class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-600 rounded-xl text-sm outline-none focus:ring-2 focus:ring-indigo-400 bg-white dark:bg-gray-700 text-gray-900 dark:text-white dark:[color-scheme:dark]">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-1.5">Até</label>
                    <div class="flex gap-2">
                        <input type="date" name="date_to" value="{{ request('date_to') }}"
                               class="flex-1 px-3 py-2.5 border border-gray-200 dark:border-gray-600 rounded-xl text-sm outline-none focus:ring-2 focus:ring-indigo-400 bg-white dark:bg-gray-700 text-gray-900 dark:text-white dark:[color-scheme:dark]">
                        <button type="submit" class="px-4 py-2.5 bg-indigo-600 text-white rounded-xl font-bold text-sm hover:bg-indigo-700 transition shadow-sm shrink-0">
                            Filtrar
                        </button>
                        @if(request()->hasAny(['company_id','status','date_from','date_to']))
                            <a href="{{ route('company.access.logs') }}" class="px-3 py-2.5 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 text-gray-500 dark:text-gray-400 rounded-xl font-bold text-sm hover:bg-gray-50 dark:hover:bg-gray-600 transition shrink-0">
                                ✕
                            </a>
                        @endif
                    </div>
                </div>

            </div>
        </form>

        <!-- Tabela -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">

            @if($logs->isEmpty())
                <div class="py-16 text-center">
                    <svg class="w-12 h-12 text-gray-200 dark:text-gray-600 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    <p class="text-gray-400 dark:text-gray-500 font-medium">Nenhum registro encontrado.</p>
                </div>
            @else
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 dark:border-gray-700 bg-gray-50/70 dark:bg-gray-700/50">
                            <th class="px-5 py-3.5 text-left text-[11px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-wider">Data / Hora</th>
                            <th class="px-5 py-3.5 text-left text-[11px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-wider">Alvo</th>
                            <th class="px-5 py-3.5 text-left text-[11px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-wider">Empresa</th>
                            <th class="px-5 py-3.5 text-left text-[11px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-wider">Funcionário</th>
                            <th class="px-5 py-3.5 text-center text-[11px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-5 py-3.5 text-left text-[11px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-wider">Motivo</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50 dark:divide-gray-700">
                        @foreach($logs as $log)
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-700/30 transition">

                                <td class="px-5 py-3.5 whitespace-nowrap">
                                    <p class="font-semibold text-gray-800 dark:text-gray-200">{{ $log->created_at->format('d/m/Y') }}</p>
                                    <p class="text-xs text-gray-400 dark:text-gray-500">{{ $log->created_at->format('H:i:s') }}</p>
                                </td>

                                <td class="px-5 py-3.5">
                                    <span class="font-mono text-xs bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 px-2 py-0.5 rounded-md">{{ $log->target }}</span>
                                </td>

                                <td class="px-5 py-3.5">
                                    @if($log->company)
                                        <a href="{{ route('company.show', $log->company_id) }}"
                                           class="font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">{{ $log->company->name }}</a>
                                    @elseif($log->app_driver_id)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-400 rounded-full text-[11px] font-black uppercase">Motorista de App</span>
                                    @else
                                        <span class="text-gray-400 dark:text-gray-500">—</span>
                                    @endif
                                </td>

                                <td class="px-5 py-3.5">
                                    @if($log->worker)
                                        <div class="flex items-center gap-2">
                                            @if($log->worker->image)
                                                <img src="{{ asset('images/' . $log->worker->image) }}" class="w-7 h-7 rounded-full object-cover">
                                            @else
                                                <div class="w-7 h-7 rounded-full bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-400 flex items-center justify-center text-xs font-black">
                                                    {{ strtoupper(substr($log->worker->name, 0, 1)) }}
                                                </div>
                                            @endif
                                            <a href="{{ route('company.worker.show', [$log->company_id, $log->company_worker_id]) }}"
                                               class="font-semibold text-gray-700 dark:text-gray-300 hover:text-indigo-600 dark:hover:text-indigo-400 hover:underline">
                                                {{ $log->worker->name }}
                                            </a>
                                        </div>
                                    @elseif($log->appDriver)
                                        <div class="flex items-center gap-2">
                                            <div class="w-7 h-7 rounded-full bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-400 flex items-center justify-center shrink-0">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 13l1.5-4.5A2 2 0 016.4 7h11.2a2 2 0 011.9 1.5L21 13m-18 0v5a1 1 0 001 1h1a1 1 0 001-1v-1h12v1a1 1 0 001 1h1a1 1 0 001-1v-5m-18 0h18M7 16h.01M17 16h.01"/></svg>
                                            </div>
                                            <span class="font-semibold text-gray-700 dark:text-gray-300">{{ $log->appDriver->name }}</span>
                                        </div>
                                    @else
                                        <span class="text-gray-400 dark:text-gray-500">—</span>
                                    @endif
                                </td>

                                <td class="px-5 py-3.5 text-center">
                                    @if($log->allowed)
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-400 rounded-full text-[11px] font-black uppercase">
                                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                            Permitido
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-400 rounded-full text-[11px] font-black uppercase">
                                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                                            Negado
                                        </span>
                                    @endif
                                </td>

                                <td class="px-5 py-3.5">
                                    @php
                                        $reasonMap = [
                                            'access_granted'    => 'Acesso liberado pelas regras',
                                            'access_denied'     => 'Bloqueado pelas regras',
                                            'worker_not_found'  => 'Funcionário não encontrado',
                                            'company_not_found' => 'Empresa não encontrada',
                                            'app_driver_access' => 'Motorista de aplicativo',
                                        ];
                                    @endphp
                                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ $reasonMap[$log->reason] ?? $log->reason ?? '—' }}</span>
                                    @if($log->obs)
                                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5 italic">{{ $log->obs }}</p>
                                    @endif
                                </td>

                            </tr>
                        @endforeach
                    </tbody>
                </table>

                @if($logs->hasPages())
                    <div class="px-5 py-4 border-t border-gray-100 dark:border-gray-700">
                        {{ $logs->links() }}
                    </div>
                @endif
            @endif
        </div>

    </div>

</x-app-layout>
