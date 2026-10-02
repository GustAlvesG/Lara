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
                    <h1 class="text-2xl font-extrabold text-ink">Histórico de Acessos</h1>
                    <p class="text-sm text-ink-2">Registro de todas as validações realizadas.</p>
                </div>
            </div>
            <div class="flex gap-2">
                @can(\App\Authorization\Permissions::EXTERNOS_CARROS_APLICATIVO)
                <a href="{{ route('company.uber.requests') }}"
                   class="px-4 py-2 bg-surface border border-line text-ink rounded-lg font-bold text-sm shadow-card hover:bg-subtle transition">
                    Pedidos de Uber
                </a>
                @endcan
                <a href="{{ route('company.access.monitor') }}"
                   class="px-4 py-2 bg-grena text-white rounded-lg font-bold text-sm shadow-card hover:bg-grena-hover transition">
                    Monitor de Acesso
                </a>
            </div>
        </div>

        <!-- Abas: todos os acessos x carros de aplicativo -->
        @php $tabBase = request()->except(['type', 'page']); @endphp
        <div class="flex gap-2 mb-6">
            <a href="{{ route('company.access.logs', array_merge($tabBase, ['type' => 'all'])) }}"
               class="px-4 py-2 rounded-xl font-bold text-sm transition {{ $type === 'all' ? 'bg-grena text-white shadow-card' : 'bg-surface border border-line text-ink-2 hover:bg-subtle' }}">
                Todos os Acessos
            </a>
            <a href="{{ route('company.access.logs', array_merge($tabBase, ['type' => 'app'])) }}"
               class="px-4 py-2 rounded-xl font-bold text-sm transition flex items-center gap-2 {{ $type === 'app' ? 'bg-grena text-white shadow-card' : 'bg-surface border border-line text-ink-2 hover:bg-subtle' }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 13l1.5-4.5A2 2 0 016.4 7h11.2a2 2 0 011.9 1.5L21 13m-18 0v5a1 1 0 001 1h1a1 1 0 001-1v-1h12v1a1 1 0 001 1h1a1 1 0 001-1v-5m-18 0h18M7 16h.01M17 16h.01"/></svg>
                Carros de Aplicativo
            </a>
        </div>

        <!-- Stats do dia -->
        <div class="grid grid-cols-3 gap-4 mb-6">
            <div class="bg-surface rounded-2xl shadow-card border border-line p-5 flex items-center gap-4">
                <div class="w-12 h-12 bg-grena-tint rounded-xl flex items-center justify-center shrink-0">
                    <svg class="w-6 h-6 text-grena-ink" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                </div>
                <div>
                    <p class="text-2xl font-black text-ink">{{ $stats['total'] }}</p>
                    <p class="text-xs text-ink-2 font-medium">Registros hoje</p>
                </div>
            </div>
            <div class="bg-surface rounded-2xl shadow-card border border-line p-5 flex items-center gap-4">
                <div class="w-12 h-12 bg-ok-soft rounded-xl flex items-center justify-center shrink-0">
                    <svg class="w-6 h-6 text-ok" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-2xl font-black text-ok">{{ $stats['allowed'] }}</p>
                    <p class="text-xs text-ink-2 font-medium">Permitidos hoje</p>
                </div>
            </div>
            <div class="bg-surface rounded-2xl shadow-card border border-line p-5 flex items-center gap-4">
                <div class="w-12 h-12 bg-danger-soft rounded-xl flex items-center justify-center shrink-0">
                    <svg class="w-6 h-6 text-danger" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-2xl font-black text-danger">{{ $stats['denied'] }}</p>
                    <p class="text-xs text-ink-2 font-medium">Negados hoje</p>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <form method="GET" action="{{ route('company.access.logs') }}"
              class="bg-surface rounded-2xl shadow-card border border-line p-5 mb-6">
            <input type="hidden" name="type" value="{{ $type }}">
            @include('companies.partials.log-search')
            <div class="grid grid-cols-2 {{ $type === 'app' ? 'md:grid-cols-3' : 'md:grid-cols-4' }} gap-4 items-end">

                {{-- Empresa não se aplica a carros de aplicativo: só a busca por data. --}}
                @if($type !== 'app')
                <div>
                    <label class="block text-xs font-bold text-ink-3 uppercase tracking-wider mb-1.5">Empresa</label>
                    <select name="company_id" class="w-full px-3 py-2.5 border border-line rounded-xl text-sm outline-none focus:ring-2 focus:ring-grena-tint bg-surface text-ink">
                        <option value="">Todas</option>
                        @foreach($companies as $company)
                            <option value="{{ $company->id }}" {{ request('company_id') == $company->id ? 'selected' : '' }}>
                                {{ $company->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                @endif

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
                        @if(request()->hasAny(['q','company_id','status','date_from','date_to']))
                            <a href="{{ route('company.access.logs', ['type' => $type]) }}" class="px-3 py-2.5 bg-surface border border-line text-ink-2 rounded-xl font-bold text-sm hover:bg-subtle transition shrink-0">
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
                    <svg class="w-12 h-12 text-ink-3 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    <p class="text-ink-3 font-medium">Nenhum registro encontrado.</p>
                </div>
            @else
                {{-- Sem este wrapper a tabela empurrava a página inteira,
                     criando scroll horizontal no body em vez de na tabela. --}}
                <div class="overflow-x-auto">
                <table class="w-full min-w-[900px] text-sm">
                    <thead>
                        <tr class="border-b border-line bg-subtle">
                            <th class="px-5 py-3.5 text-left text-[11px] font-black text-ink-3 uppercase tracking-wider">Data / Hora</th>
                            <th class="px-5 py-3.5 text-left text-[11px] font-black text-ink-3 uppercase tracking-wider">Alvo</th>
                            <th class="px-5 py-3.5 text-left text-[11px] font-black text-ink-3 uppercase tracking-wider">Empresa</th>
                            <th class="px-5 py-3.5 text-left text-[11px] font-black text-ink-3 uppercase tracking-wider">{{ $type === 'app' ? 'Solicitante' : 'Funcionário' }}</th>
                            @if($type === 'app')
                                <th class="px-5 py-3.5 text-center text-[11px] font-black text-ink-3 uppercase tracking-wider">Imagem</th>
                            @endif
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
                                    <span class="font-mono text-xs bg-subtle text-ink px-2 py-0.5 rounded-md">{{ $log->target }}</span>
                                </td>

                                <td class="px-5 py-3.5">
                                    @if($log->company)
                                        <a href="{{ route('company.show', $log->company_id) }}"
                                           class="font-semibold text-grena-ink hover:underline">{{ $log->company->name }}</a>
                                    @elseif($log->uber_access_request_id || \Illuminate\Support\Str::startsWith($log->reason ?? '', 'uber'))
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-grena-tint text-grena-ink rounded-full text-[11px] font-black uppercase">Carro de Aplicativo</span>
                                    @elseif($log->app_driver_id)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-warn-soft text-warn rounded-full text-[11px] font-black uppercase">Motorista de App</span>
                                    @elseif($log->freelancer_id)
                                        {{-- Freelancer não pertence a empresa parceira: quem o autoriza é o contrato. --}}
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-grena-tint text-grena-ink rounded-full text-[11px] font-black uppercase">Freelancer</span>
                                    @elseif($log->one_off_access_id)
                                        {{-- Liberação pontual: sem empresa, autorizada para uma entrada no dia. --}}
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-warn-soft text-warn rounded-full text-[11px] font-black uppercase">Liberação Pontual</span>
                                    @else
                                        <span class="text-ink-3">—</span>
                                    @endif
                                </td>

                                <td class="px-5 py-3.5">
                                    @if($log->worker)
                                        <div class="flex items-center gap-2">
                                            @if($log->worker->image)
                                                <img src="{{ asset('images/' . $log->worker->image) }}" class="w-7 h-7 rounded-full object-cover">
                                            @else
                                                <div class="w-7 h-7 rounded-full bg-grena-tint text-grena-ink flex items-center justify-center text-xs font-black">
                                                    {{ strtoupper(substr($log->worker->name, 0, 1)) }}
                                                </div>
                                            @endif
                                            <a href="{{ route('company.worker.show', [$log->company_id, $log->company_worker_id]) }}"
                                               class="font-semibold text-ink hover:text-grena-ink hover:underline">
                                                {{ $log->worker->name }}
                                            </a>
                                        </div>
                                    @elseif($log->uberRequest)
                                        <div class="flex items-center gap-2">
                                            <div class="w-7 h-7 rounded-full bg-grena-tint text-grena-ink flex items-center justify-center shrink-0">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                            </div>
                                            <div>
                                                <p class="font-semibold text-ink">{{ $log->uberRequest->requester_name ?? '—' }}</p>
                                                <div class="flex items-center gap-2 text-xs text-ink-3">
                                                    @if($log->uberRequest->matricula)
                                                        <span>Matrícula/CPF {{ $log->uberRequest->matricula }}</span>
                                                    @endif
                                                    @if($log->uberRequest->contact_phone)
                                                        <span class="font-mono">{{ $log->uberRequest->contact_phone }}</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @elseif($log->appDriver)
                                        <div class="flex items-center gap-2">
                                            <div class="w-7 h-7 rounded-full bg-warn-soft text-warn flex items-center justify-center shrink-0">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 13l1.5-4.5A2 2 0 016.4 7h11.2a2 2 0 011.9 1.5L21 13m-18 0v5a1 1 0 001 1h1a1 1 0 001-1v-1h12v1a1 1 0 001 1h1a1 1 0 001-1v-5m-18 0h18M7 16h.01M17 16h.01"/></svg>
                                            </div>
                                            <span class="font-semibold text-ink">{{ $log->appDriver->name }}</span>
                                        </div>
                                    @elseif($log->freelancer)
                                        <div class="flex items-center gap-2">
                                            <div class="w-7 h-7 rounded-full bg-grena-tint text-grena-ink flex items-center justify-center text-xs font-black shrink-0">
                                                {{ strtoupper(substr($log->freelancer->name, 0, 1)) }}
                                            </div>
                                            <div>
                                                @can(\App\Authorization\Permissions::FREELANCERS_CADASTRO)
                                                <a href="{{ route('freelancers.show', $log->freelancer_id) }}"
                                                   class="font-semibold text-ink hover:text-grena-ink hover:underline">
                                                    {{ $log->freelancer->name }}
                                                </a>
                                                @else
                                                <span class="font-semibold text-ink">{{ $log->freelancer->name }}</span>
                                                @endcan
                                                {{-- O contrato que liberou a entrada — ausente quando o acesso foi negado. --}}
                                                @if($log->freelancerService)
                                                    <p class="text-xs text-ink-3">
                                                        {{ $log->freelancerService->formattedPeriod() }}
                                                        @if($log->freelancerService->location)
                                                            &middot; {{ $log->freelancerService->location }}
                                                        @endif
                                                    </p>
                                                @endif
                                            </div>
                                        </div>
                                    @elseif($log->oneOffAccess)
                                        <div class="flex items-center gap-2">
                                            @if($log->oneOffAccess->imageUrl())
                                                <img src="{{ $log->oneOffAccess->imageUrl() }}" class="w-7 h-7 rounded-full object-cover shrink-0" alt="">
                                            @else
                                                <div class="w-7 h-7 rounded-full bg-warn-soft text-warn flex items-center justify-center text-xs font-black shrink-0">
                                                    {{ mb_strtoupper(mb_substr($log->oneOffAccess->name, 0, 1)) }}
                                                </div>
                                            @endif
                                            <div>
                                                @can(\App\Authorization\Permissions::EXTERNOS_LIBERACAO_PONTUAL)
                                                <a href="{{ route('company.one-off.index', ['date' => $log->oneOffAccess->access_date->toDateString()]) }}"
                                                   class="font-semibold text-ink hover:text-grena-ink hover:underline">
                                                    {{ $log->oneOffAccess->name }}
                                                </a>
                                                @else
                                                <span class="font-semibold text-ink">{{ $log->oneOffAccess->name }}</span>
                                                @endcan
                                                <p class="text-xs text-ink-3 max-w-xs truncate" title="{{ $log->oneOffAccess->reason }}">
                                                    {{ $log->oneOffAccess->reason }}
                                                    @if($log->oneOffAccess->creator)
                                                        &middot; por {{ $log->oneOffAccess->creator->name }}
                                                    @endif
                                                </p>
                                            </div>
                                        </div>
                                    @else
                                        <span class="text-ink-3">—</span>
                                    @endif
                                </td>

                                @if($type === 'app')
                                    <td class="px-5 py-3.5 text-center">
                                        @if($log->screenshot_url)
                                            <a href="{{ $log->screenshot_url }}" target="_blank" rel="noopener"
                                               class="inline-block group" title="Ver imagem da solicitação">
                                                <img src="{{ $log->screenshot_url }}" alt="Solicitação"
                                                     loading="lazy"
                                                     class="w-12 h-12 rounded-lg object-cover border border-line group-hover:ring-2 group-hover:ring-grena-tint transition mx-auto">
                                            </a>
                                        @else
                                            <span class="text-ink-3">—</span>
                                        @endif
                                    </td>
                                @endif

                                <td class="px-5 py-3.5 text-center">
                                    @if($log->allowed)
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-ok-soft text-ok rounded-full text-[11px] font-black uppercase">
                                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                            Permitido
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-danger-soft text-danger rounded-full text-[11px] font-black uppercase">
                                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                                            Negado
                                        </span>
                                    @endif
                                </td>

                                <td class="px-5 py-3.5">
                                    @php
                                        $reasonMap = [
                                            'access_granted'      => 'Acesso liberado pelas regras',
                                            'access_denied'       => 'Bloqueado pelas regras',
                                            'worker_not_found'    => 'Funcionário não encontrado',
                                            'company_not_found'   => 'Empresa não encontrada',
                                            'app_driver_access'   => 'Motorista de aplicativo',
                                            'uber_access_granted' => 'Acesso de app liberado',
                                            'uber_not_found'      => 'App não encontrado ou expirado',
                                            'uber_access_granted_manual'  => 'Liberado na fila da portaria',
                                            'uber_access_granted_expired' => 'Liberado na fila, fora do prazo',
                                            'freelancer_access_granted' => 'Liberado pelo contrato de freelancer',
                                            'freelancer_no_service'     => 'Freelancer sem serviço no horário',
                                            'one_off_access_granted'    => 'Liberação pontual',
                                            'one_off_access_used'       => 'Liberação pontual já utilizada',
                                            'one_off_access_canceled'   => 'Liberação pontual cancelada',
                                            'one_off_access_expired'    => 'Liberação pontual vencida',
                                        ];
                                    @endphp
                                    <span class="text-xs text-ink-2">{{ $reasonMap[$log->reason] ?? $log->reason ?? '—' }}</span>
                                    @if($log->obs)
                                        <p class="text-xs text-ink-3 mt-0.5 italic">{{ $log->obs }}</p>
                                    @endif
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
