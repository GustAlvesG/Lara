<x-app-layout>

    <div class="max-w-full mx-auto py-8 px-4">

        <!-- Header -->
        <div class="mb-8 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <a href="{{ route('company.access.monitor') }}" class="p-2 bg-surface rounded-xl shadow-card text-ink-3 hover:text-grena-ink border border-line transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                </a>
                <div>
                    <h1 class="text-2xl font-extrabold text-ink">Acessos de Uber Realizados</h1>
                    <p class="text-sm text-ink-2">Pedidos de Uber que efetivaram entrada na portaria.</p>
                </div>
            </div>
            @can(\App\Authorization\Permissions::EXTERNOS_HISTORICO)
            <a href="{{ route('company.access.logs') }}"
               class="px-4 py-2 bg-surface border border-line text-ink rounded-lg font-bold text-sm shadow-card hover:bg-subtle transition">
                Histórico de Acessos
            </a>
            @endcan
        </div>

        @include('companies.uber.partials.tabs', ['active' => 'accesses'])

        <!-- Stats do dia -->
        <div class="grid grid-cols-3 gap-4 mb-6">
            <div class="bg-surface rounded-2xl shadow-card border border-line p-5">
                <p class="text-2xl font-black text-ink">{{ $stats['total'] }}</p>
                <p class="text-xs text-ink-2 font-medium">Acessos hoje</p>
            </div>
            <div class="bg-surface rounded-2xl shadow-card border border-line p-5">
                <p class="text-2xl font-black text-ok">{{ $stats['allowed'] }}</p>
                <p class="text-xs text-ink-2 font-medium">Permitidos hoje</p>
            </div>
            <div class="bg-surface rounded-2xl shadow-card border border-line p-5">
                <p class="text-2xl font-black text-danger">{{ $stats['denied'] }}</p>
                <p class="text-xs text-ink-2 font-medium">Negados hoje</p>
            </div>
        </div>

        <!-- Filtros -->
        <form method="GET" action="{{ route('company.uber.accesses') }}"
              class="bg-surface rounded-2xl shadow-card border border-line p-5 mb-6">
            @include('companies.partials.log-search', ['placeholder' => 'Placa, nome de quem pediu, matrícula ou motivo'])
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4 items-end">

                <div>
                    <label class="block text-xs font-bold text-ink-3 uppercase tracking-wider mb-1.5">Status</label>
                    <select name="status" class="w-full px-3 py-2.5 border border-line rounded-xl text-sm outline-none focus:ring-2 focus:ring-grena-tint bg-surface text-ink">
                        <option value="">Todos</option>
                        <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>Permitido</option>
                        <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>Negado</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-ink-3 uppercase tracking-wider mb-1.5">De</label>
                    <input type="date" name="date_from" value="{{ request('date_from') }}"
                           class="w-full px-3 py-2.5 border border-line rounded-xl text-sm outline-none focus:ring-2 focus:ring-grena-tint bg-surface text-ink dark:[color-scheme:dark]">
                </div>

                <div>
                    <label class="block text-xs font-bold text-ink-3 uppercase tracking-wider mb-1.5">Até</label>
                    <div class="flex gap-2">
                        <input type="date" name="date_to" value="{{ request('date_to') }}"
                               class="flex-1 px-3 py-2.5 border border-line rounded-xl text-sm outline-none focus:ring-2 focus:ring-grena-tint bg-surface text-ink dark:[color-scheme:dark]">
                        <button type="submit" class="px-4 py-2.5 bg-grena text-white rounded-xl font-bold text-sm hover:bg-grena-hover transition shadow-card shrink-0">
                            Filtrar
                        </button>
                        @if(request()->hasAny(['q','status','date_from','date_to']))
                            <a href="{{ route('company.uber.accesses') }}" class="px-3 py-2.5 bg-surface border border-line text-ink-2 rounded-xl font-bold text-sm hover:bg-subtle transition shrink-0">
                                ✕
                            </a>
                        @endif
                    </div>
                </div>

            </div>
        </form>

        <!-- Tabela -->
        <div class="bg-surface rounded-2xl shadow-pop border border-line overflow-hidden">

            @if($logs->isEmpty())
                <div class="py-16 text-center">
                    <p class="text-ink-3 font-medium">Nenhum acesso encontrado.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                <table class="w-full min-w-[900px] text-sm">
                    <thead>
                        <tr class="border-b border-line bg-subtle">
                            <th class="px-5 py-3.5 text-left text-[11px] font-black text-ink-3 uppercase tracking-wider">Data / Hora</th>
                            <th class="px-5 py-3.5 text-left text-[11px] font-black text-ink-3 uppercase tracking-wider">Solicitante</th>
                            <th class="px-5 py-3.5 text-left text-[11px] font-black text-ink-3 uppercase tracking-wider">Placa</th>
                            <th class="px-5 py-3.5 text-center text-[11px] font-black text-ink-3 uppercase tracking-wider">Imagem</th>
                            <th class="px-5 py-3.5 text-center text-[11px] font-black text-ink-3 uppercase tracking-wider">Status</th>
                            <th class="px-5 py-3.5 text-left text-[11px] font-black text-ink-3 uppercase tracking-wider">Motivo</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach($logs as $log)
                            <tr class="hover:bg-subtle transition">

                                <td class="px-5 py-3.5 whitespace-nowrap">
                                    <p class="font-semibold text-ink">{{ $log->created_at->format('d/m/Y') }}</p>
                                    <p class="text-xs text-ink-3">{{ $log->created_at->format('H:i:s') }}</p>
                                </td>

                                <td class="px-5 py-3.5">
                                    @if($log->uberRequest)
                                        <p class="font-semibold text-ink">{{ $log->uberRequest->requester_name ?? '—' }}</p>
                                        <div class="flex items-center gap-2 text-xs text-ink-3">
                                            @if($log->uberRequest->matricula)
                                                <span>Matrícula/CPF {{ $log->uberRequest->matricula }}</span>
                                            @endif
                                            @if($log->uberRequest->contact_phone)
                                                <span class="font-mono">{{ $log->uberRequest->contact_phone }}</span>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-ink-3">—</span>
                                    @endif
                                </td>

                                <td class="px-5 py-3.5">
                                    <span class="font-mono text-xs bg-subtle text-ink px-2 py-0.5 rounded-md">{{ $log->target }}</span>
                                </td>

                                <td class="px-5 py-3.5 text-center">
                                    @if($log->screenshot_url)
                                        <a href="{{ $log->screenshot_url }}" target="_blank" rel="noopener"
                                           class="inline-block group" title="Ver imagem da solicitação">
                                            <img src="{{ $log->screenshot_url }}" alt="Solicitação" loading="lazy"
                                                 class="w-12 h-12 rounded-lg object-cover border border-line group-hover:ring-2 group-hover:ring-grena-tint transition mx-auto">
                                        </a>
                                    @else
                                        <span class="text-ink-3">—</span>
                                    @endif
                                </td>

                                <td class="px-5 py-3.5 text-center">
                                    @if($log->allowed)
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-ok-soft text-ok rounded-full text-[11px] font-black uppercase">Permitido</span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-danger-soft text-danger rounded-full text-[11px] font-black uppercase">Negado</span>
                                    @endif
                                </td>

                                <td class="px-5 py-3.5">
                                    @php
                                        $reasonMap = [
                                            'uber_access_granted'          => 'Acesso de app liberado',
                                            'uber_not_found'               => 'App não encontrado ou expirado',
                                            'uber_access_granted_manual'   => 'Liberado na fila da portaria',
                                            'uber_access_granted_expired'  => 'Liberado na fila, fora do prazo',
                                        ];
                                    @endphp
                                    <span class="text-xs text-ink-2">{{ $reasonMap[$log->reason] ?? $log->reason ?? '—' }}</span>
                                </td>

                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>

                @if($logs->hasPages())
                    <div class="px-5 py-4 border-t border-line">
                        {{ $logs->links() }}
                    </div>
                @endif
            @endif
        </div>

    </div>

</x-app-layout>
