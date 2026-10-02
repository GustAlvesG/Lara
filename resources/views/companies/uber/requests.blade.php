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
                    <h1 class="text-2xl font-extrabold text-ink">Pedidos de Uber</h1>
                    <p class="text-sm text-ink-2">Todos os pedidos feitos pelo WhatsApp, em qualquer status.</p>
                </div>
            </div>
            @can(\App\Authorization\Permissions::EXTERNOS_HISTORICO)
            <a href="{{ route('company.access.logs') }}"
               class="px-4 py-2 bg-surface border border-line text-ink rounded-lg font-bold text-sm shadow-card hover:bg-subtle transition">
                Histórico de Acessos
            </a>
            @endcan
        </div>

        @include('companies.uber.partials.tabs', ['active' => 'requests'])

        <!-- Stats do dia -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-surface rounded-2xl shadow-card border border-line p-5">
                <p class="text-2xl font-black text-ink">{{ $stats['total'] }}</p>
                <p class="text-xs text-ink-2 font-medium">Pedidos hoje</p>
            </div>
            <a href="{{ route('company.uber.waiting') }}"
               class="bg-surface rounded-2xl shadow-card border border-grena/40 p-5 block hover:shadow-card transition">
                <p class="text-2xl font-black text-grena-ink">{{ $stats['aguardando'] }}</p>
                <p class="text-xs text-ink-2 font-medium">Aguardando acesso &rarr;</p>
            </a>
            <div class="bg-surface rounded-2xl shadow-card border border-line p-5">
                <p class="text-2xl font-black text-ok">{{ $stats['concluido'] }}</p>
                <p class="text-xs text-ink-2 font-medium">Concluídos hoje</p>
            </div>
            <div class="bg-surface rounded-2xl shadow-card border border-line p-5">
                <p class="text-2xl font-black text-danger">{{ $stats['expirado'] }}</p>
                <p class="text-xs text-ink-2 font-medium">Expirados hoje</p>
            </div>
        </div>

        @php
            $filterKeys = ['q','status','member_validation','name','plate','matricula','phone','location','date_from','date_to'];
            $hasFilters = request()->hasAny($filterKeys);
            $inputClass = 'w-full px-3 py-2.5 border border-line rounded-xl text-sm outline-none focus:ring-2 focus:ring-grena-tint bg-surface text-ink';
            $labelClass = 'block text-xs font-bold text-ink-3 uppercase tracking-wider mb-1.5';

            // Atalhos de período: preservam os demais filtros da URL.
            $ranges = [
                'Hoje'     => [today()->toDateString(), today()->toDateString()],
                'Ontem'    => [today()->subDay()->toDateString(), today()->subDay()->toDateString()],
                '7 dias'   => [today()->subDays(6)->toDateString(), today()->toDateString()],
                '30 dias'  => [today()->subDays(29)->toDateString(), today()->toDateString()],
            ];
        @endphp

        <!-- Filtros -->
        <form method="GET" action="{{ route('company.uber.requests') }}"
              class="bg-surface rounded-2xl shadow-card border border-line p-5 mb-6">

            <!-- Busca livre -->
            <div class="flex flex-col md:flex-row gap-3 mb-4">
                <div class="flex-1">
                    <label class="{{ $labelClass }}">Busca livre</label>
                    <input type="text" name="q" value="{{ request('q') }}" autofocus
                           placeholder="Nome, placa, matrícula/CPF, telefone ou local — procura em todos de uma vez"
                           class="{{ $inputClass }}">
                </div>
                <div class="flex gap-2 items-end">
                    <button type="submit" class="px-5 py-2.5 bg-grena text-white rounded-xl font-bold text-sm hover:bg-grena-hover transition shadow-card shrink-0">
                        Filtrar
                    </button>
                    @if($hasFilters)
                        <a href="{{ route('company.uber.requests') }}"
                           class="px-4 py-2.5 bg-surface border border-line text-ink-2 rounded-xl font-bold text-sm hover:bg-subtle transition shrink-0">
                            Limpar
                        </a>
                    @endif
                </div>
            </div>

            <!-- Campos específicos -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">

                <div>
                    <label class="{{ $labelClass }}">Status</label>
                    <select name="status" class="{{ $inputClass }}">
                        <option value="">Todos</option>
                        @foreach($statuses as $value => $label)
                            <option value="{{ $value }}" {{ request('status') === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="{{ $labelClass }}">Conferência do sócio</label>
                    <select name="member_validation" class="{{ $inputClass }}">
                        <option value="">Todas</option>
                        @foreach($memberValidations as $value => $label)
                            <option value="{{ $value }}" {{ request('member_validation') === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="{{ $labelClass }}">Nome</label>
                    <input type="text" name="name" value="{{ request('name') }}" placeholder="Solicitante ou WhatsApp"
                           class="{{ $inputClass }}">
                </div>

                <div>
                    <label class="{{ $labelClass }}">Placa</label>
                    <input type="text" name="plate" value="{{ request('plate') }}" placeholder="ABC1D23" maxlength="10"
                           class="{{ $inputClass }} font-mono uppercase">
                </div>

                <div>
                    <label class="{{ $labelClass }}">Matrícula / CPF</label>
                    <input type="text" name="matricula" value="{{ request('matricula') }}" placeholder="Com ou sem máscara"
                           class="{{ $inputClass }}">
                </div>

                <div>
                    <label class="{{ $labelClass }}">Telefone</label>
                    <input type="text" name="phone" value="{{ request('phone') }}" placeholder="Só os dígitos bastam"
                           class="{{ $inputClass }}">
                </div>

                <div>
                    <label class="{{ $labelClass }}">Local do clube</label>
                    <input type="text" name="location" value="{{ request('location') }}" placeholder="Portaria, sede..."
                           class="{{ $inputClass }}">
                </div>

                <div>
                    <label class="{{ $labelClass }}">Dia (de / até)</label>
                    <div class="flex gap-2">
                        <input type="date" name="date_from" value="{{ request('date_from') }}"
                               class="{{ $inputClass }} dark:[color-scheme:dark]">
                        <input type="date" name="date_to" value="{{ request('date_to') }}"
                               class="{{ $inputClass }} dark:[color-scheme:dark]">
                    </div>
                </div>

            </div>

            <!-- Atalhos de período -->
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-[11px] font-bold text-ink-3 uppercase tracking-wider mr-1">Período</span>
                @foreach($ranges as $label => [$from, $to])
                    @php $on = request('date_from') === $from && request('date_to') === $to; @endphp
                    <a href="{{ request()->fullUrlWithQuery(['date_from' => $from, 'date_to' => $to, 'page' => null]) }}"
                       class="px-3 py-1.5 rounded-full text-xs font-bold transition {{ $on ? 'bg-grena text-white' : 'bg-subtle text-ink-2 hover:bg-line' }}">
                        {{ $label }}
                    </a>
                @endforeach
                @if(request()->hasAny(['date_from','date_to']))
                    <a href="{{ request()->fullUrlWithQuery(['date_from' => null, 'date_to' => null, 'page' => null]) }}"
                       class="px-3 py-1.5 rounded-full text-xs font-bold bg-surface border border-line text-ink-2 hover:bg-subtle transition">
                        Sem período
                    </a>
                @endif
                <span class="ml-auto text-xs font-semibold text-ink-2">
                    {{ $requests->total() }} pedido(s) encontrado(s)
                </span>
            </div>
        </form>

        @php
            $statusColors = [
                'aguardando_acesso' => 'bg-grena-tint text-grena-ink',
                'concluido'         => 'bg-ok-soft text-ok',
                'expirado'          => 'bg-danger-soft text-danger',
            ];

            $validationColors = [
                \App\Models\UberAccessRequest::MEMBER_VALIDATION_VALIDADO       => 'bg-ok-soft text-ok',
                \App\Models\UberAccessRequest::MEMBER_VALIDATION_NAO_ENCONTRADO => 'bg-warn-soft text-warn',
                \App\Models\UberAccessRequest::MEMBER_VALIDATION_INDISPONIVEL   => 'bg-subtle text-ink-2',
            ];
        @endphp

        <!-- Tabela -->
        <div class="bg-surface rounded-2xl shadow-pop border border-line overflow-hidden">

            @if($requests->isEmpty())
                <div class="py-16 text-center">
                    <p class="text-ink-3 font-medium">Nenhum pedido encontrado.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                <table class="w-full min-w-[1200px] text-sm">
                    <thead>
                        <tr class="border-b border-line bg-subtle">
                            <th class="px-5 py-3.5 text-left text-[11px] font-black text-ink-3 uppercase tracking-wider">Data / Hora</th>
                            <th class="px-5 py-3.5 text-left text-[11px] font-black text-ink-3 uppercase tracking-wider">Solicitante</th>
                            <th class="px-5 py-3.5 text-left text-[11px] font-black text-ink-3 uppercase tracking-wider">Placa</th>
                            <th class="px-5 py-3.5 text-left text-[11px] font-black text-ink-3 uppercase tracking-wider">Local</th>
                            <th class="px-5 py-3.5 text-center text-[11px] font-black text-ink-3 uppercase tracking-wider">Imagem</th>
                            <th class="px-5 py-3.5 text-center text-[11px] font-black text-ink-3 uppercase tracking-wider">Status</th>
                            <th class="px-5 py-3.5 text-left text-[11px] font-black text-ink-3 uppercase tracking-wider">Conferência</th>
                            <th class="px-5 py-3.5 text-left text-[11px] font-black text-ink-3 uppercase tracking-wider">Validade</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach($requests as $req)
                            <tr class="hover:bg-subtle transition">

                                <td class="px-5 py-3.5 whitespace-nowrap">
                                    <p class="font-semibold text-ink">{{ $req->created_at->format('d/m/Y') }}</p>
                                    <p class="text-xs text-ink-3">{{ $req->created_at->format('H:i:s') }}</p>
                                </td>

                                <td class="px-5 py-3.5">
                                    <p class="font-semibold text-ink">{{ $req->requester_name ?? '—' }}</p>
                                    <div class="flex flex-wrap items-center gap-2 text-xs text-ink-3">
                                        @if($req->matricula)
                                            <span>Matrícula/CPF {{ $req->matricula }}</span>
                                        @endif
                                        @if($req->contact_phone)
                                            <span class="font-mono">{{ $req->contact_phone }}</span>
                                        @endif
                                    </div>
                                    @if($req->contact_name_whatsapp)
                                        <p class="text-xs text-ink-3">WhatsApp: {{ $req->contact_name_whatsapp }}</p>
                                    @endif
                                </td>
                                {{-- //TODO PLATES --}}
                                <td class="px-5 py-3.5">
                                    @if($req->vehicle_plate)
                                        <x-plate :plate="$req->vehicle_plate" size="sm" /></td>
                                        {{-- <span class="font-mono text-xs bg-subtle text-ink px-2 py-0.5 rounded-md"></span> --}}
                                    @else
                                        <span class="text-ink-3">—</span>
                                    @endif
                                </td>

                                <td class="px-5 py-3.5 text-ink-2">{{ $req->club_location ?? '—' }}</td>

                                <td class="px-5 py-3.5 text-center">
                                    @if($req->screenshot_url)
                                        <a href="{{ $req->screenshot_url }}" target="_blank" rel="noopener"
                                           class="inline-block group" title="Ver imagem da solicitação">
                                            <img src="{{ $req->screenshot_url }}" alt="Solicitação" loading="lazy"
                                                 class="w-12 h-12 rounded-lg object-cover border border-line group-hover:ring-2 group-hover:ring-grena-tint transition mx-auto">
                                        </a>
                                    @else
                                        <span class="text-ink-3">—</span>
                                    @endif
                                </td>

                                <td class="px-5 py-3.5 text-center">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-black uppercase tracking-wide {{ $statusColors[$req->status] ?? 'bg-subtle text-ink-2' }}">
                                        {{ $req->statusLabel() }}
                                    </span>
                                </td>

                                <td class="px-5 py-3.5">
                                    @if($req->member_validation)
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold {{ $validationColors[$req->member_validation] ?? 'bg-subtle text-ink-2' }}">
                                            {{ $req->memberValidationLabel() }}
                                        </span>
                                        @if($req->member_validation_name)
                                            <p class="text-xs text-ink-3 mt-0.5">{{ $req->member_validation_name }}</p>
                                        @endif
                                    @else
                                        <span class="text-ink-3">—</span>
                                    @endif
                                </td>

                                <td class="px-5 py-3.5 whitespace-nowrap">
                                    @if($req->expires_at)
                                        <span class="text-xs {{ $req->expires_at->isFuture() ? 'text-ink-2' : 'text-ink-3' }}">
                                            {{ $req->expires_at->format('d/m/Y H:i') }}
                                        </span>
                                    @else
                                        <span class="text-ink-3">—</span>
                                    @endif
                                </td>

                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>

                @if($requests->hasPages())
                    <div class="px-5 py-4 border-t border-line">
                        {{ $requests->links() }}
                    </div>
                @endif
            @endif
        </div>

    </div>

</x-app-layout>
