<x-app-layout>
@php
    /**
     * Link de ordenação: clicar na coluna já ordenada inverte a direção; clicar
     * numa nova usa a direção natural dela (data, do mais recente para o mais
     * antigo; nome, de A a Z). Os demais filtros vão junto na URL, senão ordenar
     * limparia a busca.
     */
    $sortUrl = function (string $column) use ($filters) {
        $natural = $column === 'name' ? 'asc' : 'desc';
        $dir = $filters['sort'] === $column
            ? ($filters['dir'] === 'asc' ? 'desc' : 'asc')
            : $natural;

        // `page` sai fora: reordenar leva de volta à primeira página, senão a
        // pessoa cai na página 3 de uma ordem que acabou de mudar.
        return route('freelancer-services.index', array_merge(
            request()->query(),
            ['sort' => $column, 'dir' => $dir, 'page' => null]
        ));
    };

    $sortMark = fn(string $column) => $filters['sort'] === $column
        ? ($filters['dir'] === 'asc' ? '↑' : '↓')
        : '';

    $hasFilters = collect($filters)->except(['sort', 'dir'])->filter()->isNotEmpty();

    // Quem só tem a lista (a Secretaria, na matriz inicial) enxerga os
    // contratos sem valor, sem documento e sem ação — a linha não abre o
    // contrato, e as colunas de preço e de ações não aparecem. Ver
    // `freelancers.servicos.listar` x `freelancers.servicos.gerenciar`.
    $canManage = $canManage ?? false;
@endphp

<div class="py-6">
    {{-- Página de tabela larga: usa toda a largura que a sidebar deixa livre.
         Travada em max-w-7xl, a tabela cortava as últimas colunas. --}}
    <div class="max-w-full mx-auto sm:px-6 lg:px-8">

        <div class="mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h1 class="font-display text-2xl font-semibold tracking-tight text-ink">Serviços / Contratos</h1>
                <p class="text-ink-2 font-medium">Serviços a serem realizados por freelancers e o estado das assinaturas.</p>
            </div>

            @if($canManage)
            <div class="flex items-center gap-3">
                <a href="{{ route('freelancer-services.bulk') }}" class="inline-flex items-center px-4 py-3 bg-surface text-ink rounded-xl font-bold shadow-card border border-line hover:bg-subtle transition">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                    Em massa
                </a>

                <a href="{{ route('freelancer-services.create') }}" class="inline-flex items-center px-4 py-3 bg-grena text-white rounded-xl font-bold shadow-card hover:bg-grena-hover transition duration-150 transform hover:scale-[1.02]">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    Novo Serviço
                </a>
            </div>
            @endif
        </div>

        @include('freelancer.services.partials.tabs')

        @include('partials.alerts')

        {{-- Filtros. A ordenação não fica aqui: ela vive nos cabeçalhos da
             tabela, e viaja junto nos campos ocultos abaixo. --}}
        <form method="GET" action="{{ route('freelancer-services.index') }}"
              class="bg-surface rounded-2xl shadow-card border border-line p-5 mb-6">
            <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
            <input type="hidden" name="dir" value="{{ $filters['dir'] }}">

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-7 gap-4 items-end">
                <div class="sm:col-span-2 lg:col-span-1">
                    <label class="block text-xs font-bold text-ink-3 uppercase tracking-wider mb-1.5">Busca</label>
                    <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Nome, CPF ou evento/local"
                           class="w-full px-3 py-2.5 border border-line rounded-xl text-sm outline-none focus:ring-2 focus:ring-grena-tint bg-surface text-ink">
                </div>

                <div>
                    <label class="block text-xs font-bold text-ink-3 uppercase tracking-wider mb-1.5">Freelancer</label>
                    <select name="freelancer_id"
                            class="w-full px-3 py-2.5 border border-line rounded-xl text-sm outline-none focus:ring-2 focus:ring-grena-tint bg-surface text-ink">
                        <option value="">Todos</option>
                        @foreach($freelancers as $freelancer)
                            <option value="{{ $freelancer->id }}" @selected((string) $filters['freelancer_id'] === (string) $freelancer->id)>
                                {{ $freelancer->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-ink-3 uppercase tracking-wider mb-1.5">Serviço</label>
                    <select name="function_id"
                            class="w-full px-3 py-2.5 border border-line rounded-xl text-sm outline-none focus:ring-2 focus:ring-grena-tint bg-surface text-ink">
                        <option value="">Todos</option>
                        @foreach($functions as $function)
                            <option value="{{ $function->id }}" @selected((string) $filters['function_id'] === (string) $function->id)>
                                {{ $function->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-ink-3 uppercase tracking-wider mb-1.5">Assinatura</label>
                    <select name="signature"
                            class="w-full px-3 py-2.5 border border-line rounded-xl text-sm outline-none focus:ring-2 focus:ring-grena-tint bg-surface text-ink">
                        <option value="">Qualquer</option>
                        @foreach($signatureFilters as $value => $label)
                            <option value="{{ $value }}" @selected($filters['signature'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Os dois desvios que a tela já marca (⚠️ e 🕒), agora
                     procuráveis: é assim que se vai atrás deles depois. --}}
                <div>
                    <label class="block text-xs font-bold text-ink-3 uppercase tracking-wider mb-1.5">Registro</label>
                    <select name="issue"
                            class="w-full px-3 py-2.5 border border-line rounded-xl text-sm outline-none focus:ring-2 focus:ring-grena-tint bg-surface text-ink">
                        <option value="">Todos</option>
                        @foreach($issueFilters as $value => $label)
                            <option value="{{ $value }}" @selected($filters['issue'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- A redação das cláusulas que o contrato firmou. O jurídico
                     revisa o texto de tempos em tempos, e é por aqui que se
                     varre quem foi assinado sob a redação antiga. --}}
                <div>
                    <label class="block text-xs font-bold text-ink-3 uppercase tracking-wider mb-1.5">Redação</label>
                    <select name="contract_version"
                            class="w-full px-3 py-2.5 border border-line rounded-xl text-sm outline-none focus:ring-2 focus:ring-grena-tint bg-surface text-ink">
                        <option value="">Qualquer</option>
                        @foreach($contractVersionFilters as $value => $label)
                            <option value="{{ $value }}" @selected($filters['contract_version'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-ink-3 uppercase tracking-wider mb-1.5">Período · de</label>
                    <input type="date" name="from" value="{{ $filters['from'] }}"
                           class="w-full px-3 py-2.5 border border-line rounded-xl text-sm outline-none focus:ring-2 focus:ring-grena-tint bg-surface text-ink dark:[color-scheme:dark]">
                </div>

                <div>
                    <label class="block text-xs font-bold text-ink-3 uppercase tracking-wider mb-1.5">Até</label>
                    <input type="date" name="to" value="{{ $filters['to'] }}"
                           class="w-full px-3 py-2.5 border border-line rounded-xl text-sm outline-none focus:ring-2 focus:ring-grena-tint bg-surface text-ink dark:[color-scheme:dark]">
                </div>
            </div>

            <div class="mt-4 flex items-center justify-end gap-2">
                @if($hasFilters)
                    <a href="{{ route('freelancer-services.index') }}"
                       class="px-4 py-2.5 bg-surface border border-line text-ink-2 rounded-xl font-bold text-sm hover:bg-subtle transition">
                        Limpar filtros
                    </a>
                @endif
                <button type="submit" class="px-6 py-2.5 bg-grena text-white rounded-xl font-bold text-sm hover:bg-grena-hover transition shadow-card">
                    Filtrar
                </button>
            </div>
        </form>

        <div class="bg-surface rounded-2xl shadow-pop border border-line overflow-hidden">
            @if($services->isEmpty())
                {{-- Página vazia além da última (URL editada, ou registros que
                     sumiram enquanto a tela estava aberta) não é o mesmo que
                     lista vazia: dizer "nenhum serviço registrado" com 58 na
                     tabela seria mentira. --}}
                @php $pastLastPage = $services->currentPage() > 1 && $services->total() > 0; @endphp
                <div class="p-12 text-center">
                    <p class="text-ink-2">
                        @if($pastLastPage)
                            Esta página não tem contratos — a lista tem {{ $services->total() }}.
                        @else
                            {{ $hasFilters ? 'Nenhum serviço encontrado com esses filtros.' : 'Nenhum serviço registrado.' }}
                        @endif
                    </p>
                    @if($pastLastPage)
                        <a href="{{ route('freelancer-services.index', array_merge(request()->query(), ['page' => null])) }}"
                           class="inline-block mt-3 text-sm font-bold text-grena-ink hover:underline">
                            Voltar à primeira página
                        </a>
                    @elseif($hasFilters)
                        <a href="{{ route('freelancer-services.index') }}" class="inline-block mt-3 text-sm font-bold text-grena-ink hover:underline">
                            Limpar filtros
                        </a>
                    @endif
                </div>
            @else
                <div class="px-4 py-3 border-b border-line text-xs font-bold text-ink-3 uppercase tracking-wider">
                    {{ $services->total() }} {{ $services->total() === 1 ? 'contrato' : 'contratos' }}
                    @if($services->hasPages())
                        · exibindo {{ $services->firstItem() }}–{{ $services->lastItem() }}
                    @endif
                    · ordenado por {{ $filters['sort'] === 'name' ? 'nome' : 'data' }}
                    ({{ $filters['dir'] === 'asc' ? 'crescente' : 'decrescente' }})
                </div>

                <div class="overflow-x-auto" x-data>
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs font-bold text-ink-3 uppercase tracking-wider bg-subtle">
                            <tr>
                                <th class="px-4 py-3"></th>
                                <th class="px-4 py-3">Nº</th>
                                <th class="px-4 py-3">
                                    <a href="{{ $sortUrl('name') }}" class="inline-flex items-center gap-1 hover:text-ink-2 transition">
                                        Freelancer <span class="text-grena-ink">{{ $sortMark('name') }}</span>
                                    </a>
                                </th>
                                <th class="px-4 py-3">Função</th>
                                <th class="px-4 py-3">Evento/Local</th>
                                <th class="px-4 py-3">
                                    <a href="{{ $sortUrl('date') }}" class="inline-flex items-center gap-1 hover:text-ink-2 transition">
                                        Período <span class="text-grena-ink">{{ $sortMark('date') }}</span>
                                    </a>
                                </th>
                                <th class="px-4 py-3">Duração</th>
                                @if($canManage)
                                <th class="px-4 py-3">Preço</th>
                                @endif
                                <th class="px-4 py-3">Contrato</th>
                                @if($canManage)
                                <th class="px-4 py-3 text-right">Ações</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach($services as $service)
                            @php $exceeds = $excessFlags[$service->id] ?? false; @endphp
                            {{-- A linha inteira abre o contrato. Cliques em link, botão ou
                                 formulário (Excluir) continuam sendo deles, e um clique que
                                 só selecionou texto não navega. --}}
                            <tr class="{{ $canManage ? 'cursor-pointer' : '' }} hover:bg-subtle transition {{ $exceeds ? 'bg-warn-soft' : '' }} {{ $service->isCancelled() ? 'opacity-60' : '' }}"
                                @if($canManage)
                                tabindex="0"
                                x-on:click="if (!$event.target.closest('a, button, form') && !window.getSelection().toString()) window.location = '{{ route('freelancer-services.show', $service) }}'"
                                x-on:keydown.enter="window.location = '{{ route('freelancer-services.show', $service) }}'"
                                @endif>
                                <td class="px-4 py-4">
                                    @if($exceeds)
                                        <span title="Freelancer com mais de {{ \App\Models\FreelancerService::WEEKLY_LIMIT }} serviços numa janela de 7 dias" class="text-warn">⚠️</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 font-mono text-xs text-ink-3 whitespace-nowrap">#{{ $service->id }}</td>
                                <td class="px-4 py-4 font-semibold text-ink">{{ $service->freelancer->name }}</td>
                                <td class="px-4 py-4 text-ink">{{ $service->functionFreelancer->name }}</td>
                                <td class="px-4 py-4 text-ink">
                                    {{ $service->location ?? '—' }}
                                    @if(filled($service->description))
                                        <span class="block max-w-[16rem] truncate text-xs text-ink-3" title="{{ $service->description }}">{{ $service->description }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 text-ink whitespace-nowrap">
                                    {{ $service->start_date->format('d/m/Y') }}
                                    <span class="text-ink-3">
                                        {{ substr($service->start_time, 0, 5) }}–{{ substr($service->end_time, 0, 5) }}
                                    </span>
                                    @if($service->start_date->ne($service->end_date))
                                        <span title="Termina no dia seguinte" class="text-warn">+1</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 text-ink">{{ $service->formattedDuration() }}</td>
                                @if($canManage)
                                <td class="px-4 py-4 text-ink whitespace-nowrap">
                                    R$ {{ number_format($service->price, 2, ',', '.') }}
                                    {{-- Valor digitado no registro: não bate com duração × função,
                                         e a marca evita que pareça erro de cálculo. --}}
                                    @if($service->isFixedPrice() && !$service->isCommissionAmendment())
                                        <span class="block text-xs font-bold text-ink-3"
                                              title="Valor fixo, digitado no registro do contrato — não calculado pelas horas do turno">Valor fixo</span>
                                    @endif
                                </td>
                                @endif
                                <td class="px-4 py-4">
                                    <x-freelancer-signature-badge :service="$service" />
                                    {{-- O par aditivo/aditivado explica por que duas linhas do mesmo
                                         turno aparecem na lista e só uma delas será paga. --}}
                                    @if($service->isCommissionAmendment())
                                        <span class="block text-xs font-bold text-ok"
                                              title="{{ $canManage ? 'Comissão sobre R$ ' . number_format((float) $service->sales_amount, 2, ',', '.') . ' vendidos, paga' : 'Comissão paga' }} além do contrato #{{ $service->parent_service_id }}">Comissão de venda</span>
                                    @elseif($service->isAmendment())
                                        <span class="block text-xs font-bold text-grena-ink"
                                              title="Aditivo do contrato #{{ $service->parent_service_id }}">Aditivo</span>
                                    @elseif($service->isAmended())
                                        <span class="block text-xs font-bold text-ink-3"
                                              title="Assinado normalmente; o pagamento do turno é feito pelo aditivo #{{ $service->amendment_service_id }}">Aditivado · pago no aditivo</span>
                                    @endif
                                    {{-- Mesma informação das tarjas da tela do contrato, em selo. --}}
                                    @if($service->isSignedAfterStart())
                                        <span class="block mt-1 text-xs font-bold text-danger whitespace-nowrap"
                                              title="O turno começou em {{ $service->startsAt()->format('d/m/Y H:i') }} e a assinatura do freelancer foi registrada em {{ $service->freelancer_signed_at->format('d/m/Y H:i') }}. Tolerância de {{ \App\Models\FreelancerService::SIGNATURE_TOLERANCE_MINUTES }} minutos.">
                                            🕒 Assinado {{ $service->formattedSignatureDelay() }} após o início
                                        </span>
                                    @elseif($service->isUnsignedAfterStart())
                                        <span class="block mt-1 text-xs font-bold text-danger whitespace-nowrap"
                                              title="O turno começou em {{ $service->startsAt()->format('d/m/Y H:i') }} e o freelancer nunca assinou este contrato.">
                                            ⏳ Sem assinatura · turno começou há {{ $service->formattedTimeSinceStart() }}
                                        </span>
                                    @endif
                                </td>
                                @if($canManage)
                                <td class="px-4 py-4 text-right space-x-3 whitespace-nowrap">
                                    <a href="{{ route('freelancer-services.show', $service) }}" class="text-grena-ink hover:underline font-medium text-xs">
                                        {{ $service->canBeUpdated() ? 'Editar' : 'Ver' }}
                                    </a>
                                    @if($service->canBeDeleted())
                                        <form method="POST" action="{{ route('freelancer-services.destroy', $service) }}" class="inline"
                                              onsubmit="return confirm('Excluir este serviço?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-danger hover:underline font-medium text-xs">Excluir</button>
                                        </form>
                                    @endif
                                </td>
                                @endif
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($services->hasPages())
                    <div class="px-5 py-4 border-t border-line">
                        {{ $services->links() }}
                    </div>
                @endif
            @endif
        </div>
    </div>
</div>
</x-app-layout>
