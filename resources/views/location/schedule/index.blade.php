<x-app-layout>

    <div class="max-w-7xl mx-auto pt-4 pb-10 px-4">

        <!-- HEADER -->
        <div class="mb-8 flex items-center justify-between flex-wrap gap-4">
            <div class="flex items-center gap-4">
                <a href="{{ route('schedule.index') }}" class="p-2 bg-white rounded-xl shadow-md text-gray-400 hover:text-indigo-600 border border-gray-100 transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                </a>
                <div>
                    <h1 class="text-3xl font-extrabold text-gray-900 leading-tight">Todos os Agendamentos</h1>
                    <p class="text-gray-500 font-medium">Consulte reservas em qualquer status, mesmo canceladas ou expiradas.</p>
                </div>
            </div>
            <a href="{{ route('schedule.index') }}"
               class="px-4 py-2.5 bg-gray-900 text-white rounded-xl font-black text-sm uppercase tracking-wide shadow-md hover:bg-indigo-600 transition">
                Novo Agendamento
            </a>
        </div>

        <!-- FILTROS -->
        <form method="GET" action="{{ route('schedule.list') }}"
              class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 mb-6">
            <div class="grid grid-cols-2 md:grid-cols-6 gap-4 items-end">

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
                    <label class="block text-xs font-black text-gray-400 uppercase tracking-wider mb-1.5">Local</label>
                    <select name="place_id" class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm outline-none focus:ring-2 focus:ring-indigo-400">
                        <option value="">Todos</option>
                        @foreach($places as $place)
                            <option value="{{ $place->id }}" {{ (string) request('place_id') === (string) $place->id ? 'selected' : '' }}>
                                {{ optional($place->group)->name }} - {{ $place->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-black text-gray-400 uppercase tracking-wider mb-1.5">De</label>
                    <input type="date" name="date_from" value="{{ request('date_from') }}"
                           class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm outline-none focus:ring-2 focus:ring-indigo-400">
                </div>

                <div>
                    <label class="block text-xs font-black text-gray-400 uppercase tracking-wider mb-1.5">Até</label>
                    <div class="flex gap-2">
                        <input type="date" name="date_to" value="{{ request('date_to') }}"
                               class="flex-1 px-3 py-2.5 border border-gray-200 rounded-xl text-sm outline-none focus:ring-2 focus:ring-indigo-400">
                        <button type="submit" class="px-4 py-2.5 bg-indigo-600 text-white rounded-xl font-bold text-sm hover:bg-indigo-700 transition shadow-sm shrink-0">
                            Filtrar
                        </button>
                        @if(request()->hasAny(['member','status_id','place_id','date_from','date_to']))
                            <a href="{{ route('schedule.list') }}" class="px-3 py-2.5 bg-white border border-gray-200 text-gray-500 rounded-xl font-bold text-sm hover:bg-gray-50 transition shrink-0">
                                ✕
                            </a>
                        @endif
                    </div>
                </div>

            </div>
        </form>

        <!-- TABELA -->
        <div class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">

            @if($schedules->isEmpty())
                <div class="py-16 text-center">
                    <svg class="w-12 h-12 text-gray-200 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    <p class="text-gray-400 font-medium">Nenhum agendamento encontrado com esses filtros.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50/70">
                                <th class="px-5 py-3.5 text-left text-[11px] font-black text-gray-400 uppercase tracking-wider">#</th>
                                <th class="px-5 py-3.5 text-left text-[11px] font-black text-gray-400 uppercase tracking-wider">Sócio</th>
                                <th class="px-5 py-3.5 text-left text-[11px] font-black text-gray-400 uppercase tracking-wider">Local</th>
                                <th class="px-5 py-3.5 text-left text-[11px] font-black text-gray-400 uppercase tracking-wider">Data / Horário</th>
                                <th class="px-5 py-3.5 text-center text-[11px] font-black text-gray-400 uppercase tracking-wider">Status</th>
                                <th class="px-5 py-3.5 text-right text-[11px] font-black text-gray-400 uppercase tracking-wider">Valor</th>
                                <th class="px-5 py-3.5"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach($schedules as $schedule)
                                @php
                                    $statusConfig = match((int) $schedule->status_id) {
                                        1 => ['bg' => 'bg-green-100 text-green-700', 'text' => 'Confirmada'],
                                        3 => ['bg' => 'bg-amber-100 text-amber-700', 'text' => 'Pendente'],
                                        0 => ['bg' => 'bg-red-100 text-red-700', 'text' => 'Cancelada'],
                                        4 => ['bg' => 'bg-gray-200 text-gray-600', 'text' => 'Expirada'],
                                        10 => ['bg' => 'bg-gray-200 text-gray-600', 'text' => 'Antiga'],
                                        default => ['bg' => 'bg-gray-100 text-gray-500', 'text' => $schedule->status->portuguese ?? '?'],
                                    };
                                @endphp
                                <tr class="hover:bg-gray-50/50 transition">
                                    <td class="px-5 py-3.5 font-mono text-xs text-gray-400">#{{ $schedule->id }}</td>
                                    <td class="px-5 py-3.5">
                                        <p class="font-semibold text-gray-800">{{ optional($schedule->member)->name ?? 'Sócio não identificado' }}</p>
                                        <p class="text-xs text-gray-400">{{ optional($schedule->member)->cpf }}</p>
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <p class="font-semibold text-gray-700">{{ optional($schedule->place)->name ?? 'Local removido' }}</p>
                                        <p class="text-xs text-gray-400">{{ optional(optional($schedule->place)->group)->name }}</p>
                                    </td>
                                    <td class="px-5 py-3.5 whitespace-nowrap">
                                        <p class="font-semibold text-gray-800">{{ \Carbon\Carbon::parse($schedule->start_schedule)->format('d/m/Y') }}</p>
                                        <p class="text-xs text-gray-400">
                                            {{ \Carbon\Carbon::parse($schedule->start_schedule)->format('H:i') }}
                                            às
                                            {{ \Carbon\Carbon::parse($schedule->end_schedule)->format('H:i') }}
                                        </p>
                                    </td>
                                    <td class="px-5 py-3.5 text-center">
                                        <span class="inline-block px-2.5 py-1 rounded-full text-[11px] font-black uppercase {{ $statusConfig['bg'] }}">
                                            {{ $statusConfig['text'] }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3.5 text-right font-semibold text-gray-700">
                                        R$ {{ number_format($schedule->price ?? 0, 2, ',', '.') }}
                                    </td>
                                    <td class="px-5 py-3.5 text-right">
                                        <a href="{{ route('schedule.show', $schedule->id) }}"
                                           class="inline-flex items-center gap-1 px-3 py-1.5 bg-gray-900 text-white rounded-lg text-xs font-bold hover:bg-indigo-600 transition">
                                            Ver detalhes
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($schedules->hasPages())
                    <div class="px-5 py-4 border-t border-gray-100">
                        {{ $schedules->links() }}
                    </div>
                @endif
            @endif
        </div>

    </div>

</x-app-layout>
