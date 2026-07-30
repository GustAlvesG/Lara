<x-app-layout>

    <div class="max-w-full mx-auto py-8 px-4">

        <!-- Header -->
        <div class="mb-8 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <a href="{{ route('company.access.monitor') }}" class="p-2 bg-white dark:bg-gray-800 rounded-xl shadow-md text-gray-400 dark:text-gray-500 hover:text-indigo-600 dark:hover:text-indigo-400 border border-gray-100 dark:border-gray-700 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                </a>
                <div>
                    <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white">Pedidos de Uber</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Todos os pedidos feitos pelo WhatsApp, em qualquer status.</p>
                </div>
            </div>
            <a href="{{ route('company.access.logs') }}"
               class="px-4 py-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg font-bold text-sm shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                Histórico de Acessos
            </a>
        </div>

        @include('companies.uber.partials.tabs', ['active' => 'requests'])

        <!-- Stats do dia -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
                <p class="text-2xl font-black text-gray-900 dark:text-white">{{ $stats['total'] }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">Pedidos hoje</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
                <p class="text-2xl font-black text-indigo-600 dark:text-indigo-400">{{ $stats['aguardando'] }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">Aguardando acesso</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
                <p class="text-2xl font-black text-green-700 dark:text-green-400">{{ $stats['concluido'] }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">Concluídos hoje</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
                <p class="text-2xl font-black text-red-600 dark:text-red-400">{{ $stats['expirado'] }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">Expirados hoje</p>
            </div>
        </div>

        <!-- Filtros -->
        <form method="GET" action="{{ route('company.uber.requests') }}"
              class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-5 mb-6">
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4 items-end">

                <div>
                    <label class="block text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-1.5">Status</label>
                    <select name="status" class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-600 rounded-xl text-sm outline-none focus:ring-2 focus:ring-indigo-400 bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                        <option value="">Todos</option>
                        @foreach($statuses as $value => $label)
                            <option value="{{ $value }}" {{ request('status') === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
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
                        @if(request()->hasAny(['status','date_from','date_to']))
                            <a href="{{ route('company.uber.requests') }}" class="px-3 py-2.5 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 text-gray-500 dark:text-gray-400 rounded-xl font-bold text-sm hover:bg-gray-50 dark:hover:bg-gray-600 transition shrink-0">
                                ✕
                            </a>
                        @endif
                    </div>
                </div>

            </div>
        </form>

        @php
            $statusColors = [
                'aguardando_acesso' => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-400',
                'concluido'         => 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400',
                'expirado'          => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400',
            ];
        @endphp

        <!-- Tabela -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">

            @if($requests->isEmpty())
                <div class="py-16 text-center">
                    <p class="text-gray-400 dark:text-gray-500 font-medium">Nenhum pedido encontrado.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                <table class="w-full min-w-[1000px] text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 dark:border-gray-700 bg-gray-50/70 dark:bg-gray-700/50">
                            <th class="px-5 py-3.5 text-left text-[11px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-wider">Data / Hora</th>
                            <th class="px-5 py-3.5 text-left text-[11px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-wider">Solicitante</th>
                            <th class="px-5 py-3.5 text-left text-[11px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-wider">Placa</th>
                            <th class="px-5 py-3.5 text-left text-[11px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-wider">Local</th>
                            <th class="px-5 py-3.5 text-center text-[11px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-wider">Imagem</th>
                            <th class="px-5 py-3.5 text-center text-[11px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-5 py-3.5 text-left text-[11px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-wider">Validade</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50 dark:divide-gray-700">
                        @foreach($requests as $req)
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-700/30 transition">

                                <td class="px-5 py-3.5 whitespace-nowrap">
                                    <p class="font-semibold text-gray-800 dark:text-gray-200">{{ $req->created_at->format('d/m/Y') }}</p>
                                    <p class="text-xs text-gray-400 dark:text-gray-500">{{ $req->created_at->format('H:i:s') }}</p>
                                </td>

                                <td class="px-5 py-3.5">
                                    <p class="font-semibold text-gray-700 dark:text-gray-300">{{ $req->requester_name ?? '—' }}</p>
                                    <div class="flex items-center gap-2 text-xs text-gray-400 dark:text-gray-500">
                                        @if($req->matricula)
                                            <span>Matrícula {{ $req->matricula }}</span>
                                        @endif
                                        @if($req->contact_phone)
                                            <span class="font-mono">{{ $req->contact_phone }}</span>
                                        @endif
                                    </div>
                                </td>

                                <td class="px-5 py-3.5">
                                    @if($req->vehicle_plate)
                                        <span class="font-mono text-xs bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 px-2 py-0.5 rounded-md">{{ $req->vehicle_plate }}</span>
                                    @else
                                        <span class="text-gray-300 dark:text-gray-600">—</span>
                                    @endif
                                </td>

                                <td class="px-5 py-3.5 text-gray-600 dark:text-gray-400">{{ $req->club_location ?? '—' }}</td>

                                <td class="px-5 py-3.5 text-center">
                                    @if($req->screenshot_url)
                                        <a href="{{ $req->screenshot_url }}" target="_blank" rel="noopener"
                                           class="inline-block group" title="Ver imagem da solicitação">
                                            <img src="{{ $req->screenshot_url }}" alt="Solicitação" loading="lazy"
                                                 class="w-12 h-12 rounded-lg object-cover border border-gray-200 dark:border-gray-600 group-hover:ring-2 group-hover:ring-indigo-400 transition mx-auto">
                                        </a>
                                    @else
                                        <span class="text-gray-300 dark:text-gray-600">—</span>
                                    @endif
                                </td>

                                <td class="px-5 py-3.5 text-center">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-black uppercase tracking-wide {{ $statusColors[$req->status] ?? 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' }}">
                                        {{ $req->statusLabel() }}
                                    </span>
                                </td>

                                <td class="px-5 py-3.5 whitespace-nowrap">
                                    @if($req->expires_at)
                                        <span class="text-xs {{ $req->expires_at->isFuture() ? 'text-gray-600 dark:text-gray-400' : 'text-gray-400 dark:text-gray-500' }}">
                                            {{ $req->expires_at->format('d/m/Y H:i') }}
                                        </span>
                                    @else
                                        <span class="text-gray-300 dark:text-gray-600">—</span>
                                    @endif
                                </td>

                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>

                @if($requests->hasPages())
                    <div class="px-5 py-4 border-t border-gray-100 dark:border-gray-700">
                        {{ $requests->links() }}
                    </div>
                @endif
            @endif
        </div>

    </div>

</x-app-layout>
