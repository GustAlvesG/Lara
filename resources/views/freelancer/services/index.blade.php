<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Serviços / Contratos') }}
        </h2>
    </x-slot>

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
@endphp

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    {{-- Página de tabela larga: usa toda a largura que a sidebar deixa livre.
         Travada em max-w-7xl, a tabela cortava as últimas colunas. --}}
    <div class="max-w-full mx-auto sm:px-6 lg:px-8">

        <div class="mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white leading-tight">Serviços / Contratos</h1>
                <p class="text-gray-500 dark:text-gray-400 font-medium">Serviços a serem realizados por freelancers e o estado das assinaturas.</p>
            </div>

            <div class="flex items-center gap-3">
                <a href="{{ route('freelancer-services.bulk') }}" class="inline-flex items-center px-4 py-3 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 rounded-xl font-bold shadow border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                    Em massa
                </a>

                <a href="{{ route('freelancer-services.create') }}" class="inline-flex items-center px-4 py-3 bg-[#A00001] text-white rounded-xl font-bold shadow-lg hover:bg-[#800000] transition duration-150 transform hover:scale-[1.02]">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    Novo Serviço
                </a>
            </div>
        </div>

        @include('freelancer.services.partials.tabs')

        @include('partials.alerts')

        {{-- Filtros. A ordenação não fica aqui: ela vive nos cabeçalhos da
             tabela, e viaja junto nos campos ocultos abaixo. --}}
        <form method="GET" action="{{ route('freelancer-services.index') }}"
              class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-5 mb-6">
            <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
            <input type="hidden" name="dir" value="{{ $filters['dir'] }}">

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-7 gap-4 items-end">
                <div class="sm:col-span-2 lg:col-span-1">
                    <label class="block text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-1.5">Busca</label>
                    <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Nome, CPF ou evento/local"
                           class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-600 rounded-xl text-sm outline-none focus:ring-2 focus:ring-indigo-400 bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-1.5">Freelancer</label>
                    <select name="freelancer_id"
                            class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-600 rounded-xl text-sm outline-none focus:ring-2 focus:ring-indigo-400 bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                        <option value="">Todos</option>
                        @foreach($freelancers as $freelancer)
                            <option value="{{ $freelancer->id }}" @selected((string) $filters['freelancer_id'] === (string) $freelancer->id)>
                                {{ $freelancer->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-1.5">Serviço</label>
                    <select name="function_id"
                            class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-600 rounded-xl text-sm outline-none focus:ring-2 focus:ring-indigo-400 bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                        <option value="">Todos</option>
                        @foreach($functions as $function)
                            <option value="{{ $function->id }}" @selected((string) $filters['function_id'] === (string) $function->id)>
                                {{ $function->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-1.5">Assinatura</label>
                    <select name="signature"
                            class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-600 rounded-xl text-sm outline-none focus:ring-2 focus:ring-indigo-400 bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                        <option value="">Qualquer</option>
                        @foreach($signatureFilters as $value => $label)
                            <option value="{{ $value }}" @selected($filters['signature'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Os dois desvios que a tela já marca (⚠️ e 🕒), agora
                     procuráveis: é assim que se vai atrás deles depois. --}}
                <div>
                    <label class="block text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-1.5">Registro</label>
                    <select name="issue"
                            class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-600 rounded-xl text-sm outline-none focus:ring-2 focus:ring-indigo-400 bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                        <option value="">Todos</option>
                        @foreach($issueFilters as $value => $label)
                            <option value="{{ $value }}" @selected($filters['issue'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-1.5">Período · de</label>
                    <input type="date" name="from" value="{{ $filters['from'] }}"
                           class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-600 rounded-xl text-sm outline-none focus:ring-2 focus:ring-indigo-400 bg-white dark:bg-gray-700 text-gray-900 dark:text-white dark:[color-scheme:dark]">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-1.5">Até</label>
                    <input type="date" name="to" value="{{ $filters['to'] }}"
                           class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-600 rounded-xl text-sm outline-none focus:ring-2 focus:ring-indigo-400 bg-white dark:bg-gray-700 text-gray-900 dark:text-white dark:[color-scheme:dark]">
                </div>
            </div>

            <div class="mt-4 flex items-center justify-end gap-2">
                @if($hasFilters)
                    <a href="{{ route('freelancer-services.index') }}"
                       class="px-4 py-2.5 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 text-gray-500 dark:text-gray-400 rounded-xl font-bold text-sm hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                        Limpar filtros
                    </a>
                @endif
                <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white rounded-xl font-bold text-sm hover:bg-indigo-700 transition shadow-sm">
                    Filtrar
                </button>
            </div>
        </form>

        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
            @if($services->isEmpty())
                {{-- Página vazia além da última (URL editada, ou registros que
                     sumiram enquanto a tela estava aberta) não é o mesmo que
                     lista vazia: dizer "nenhum serviço registrado" com 58 na
                     tabela seria mentira. --}}
                @php $pastLastPage = $services->currentPage() > 1 && $services->total() > 0; @endphp
                <div class="p-12 text-center">
                    <p class="text-gray-500 dark:text-gray-400">
                        @if($pastLastPage)
                            Esta página não tem contratos — a lista tem {{ $services->total() }}.
                        @else
                            {{ $hasFilters ? 'Nenhum serviço encontrado com esses filtros.' : 'Nenhum serviço registrado.' }}
                        @endif
                    </p>
                    @if($pastLastPage)
                        <a href="{{ route('freelancer-services.index', array_merge(request()->query(), ['page' => null])) }}"
                           class="inline-block mt-3 text-sm font-bold text-indigo-600 dark:text-indigo-400 hover:underline">
                            Voltar à primeira página
                        </a>
                    @elseif($hasFilters)
                        <a href="{{ route('freelancer-services.index') }}" class="inline-block mt-3 text-sm font-bold text-indigo-600 dark:text-indigo-400 hover:underline">
                            Limpar filtros
                        </a>
                    @endif
                </div>
            @else
                <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider">
                    {{ $services->total() }} {{ $services->total() === 1 ? 'contrato' : 'contratos' }}
                    @if($services->hasPages())
                        · exibindo {{ $services->firstItem() }}–{{ $services->lastItem() }}
                    @endif
                    · ordenado por {{ $filters['sort'] === 'name' ? 'nome' : 'data' }}
                    ({{ $filters['dir'] === 'asc' ? 'crescente' : 'decrescente' }})
                </div>

                <div class="overflow-x-auto" x-data>
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider bg-gray-50 dark:bg-gray-900/40">
                            <tr>
                                <th class="px-4 py-3"></th>
                                <th class="px-4 py-3">
                                    <a href="{{ $sortUrl('name') }}" class="inline-flex items-center gap-1 hover:text-gray-600 dark:hover:text-gray-300 transition">
                                        Freelancer <span class="text-[#A00001] dark:text-red-400">{{ $sortMark('name') }}</span>
                                    </a>
                                </th>
                                <th class="px-4 py-3">Função</th>
                                <th class="px-4 py-3">Evento/Local</th>
                                <th class="px-4 py-3">
                                    <a href="{{ $sortUrl('date') }}" class="inline-flex items-center gap-1 hover:text-gray-600 dark:hover:text-gray-300 transition">
                                        Período <span class="text-[#A00001] dark:text-red-400">{{ $sortMark('date') }}</span>
                                    </a>
                                </th>
                                <th class="px-4 py-3">Duração</th>
                                <th class="px-4 py-3">Preço</th>
                                <th class="px-4 py-3">Contrato</th>
                                <th class="px-4 py-3 text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach($services as $service)
                            @php $exceeds = $excessFlags[$service->id] ?? false; @endphp
                            {{-- A linha inteira abre o contrato. Cliques em link, botão ou
                                 formulário (Excluir) continuam sendo deles, e um clique que
                                 só selecionou texto não navega. --}}
                            <tr class="cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50 transition {{ $exceeds ? 'bg-amber-50 dark:bg-amber-900/10' : '' }} {{ $service->isCancelled() ? 'opacity-60' : '' }}"
                                tabindex="0"
                                x-on:click="if (!$event.target.closest('a, button, form') && !window.getSelection().toString()) window.location = '{{ route('freelancer-services.show', $service) }}'"
                                x-on:keydown.enter="window.location = '{{ route('freelancer-services.show', $service) }}'">
                                <td class="px-4 py-4">
                                    @if($exceeds)
                                        <span title="Freelancer com mais de {{ \App\Models\FreelancerService::WEEKLY_LIMIT }} serviços numa janela de 7 dias" class="text-amber-500">⚠️</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 font-semibold text-gray-900 dark:text-white">{{ $service->freelancer->name }}</td>
                                <td class="px-4 py-4 text-gray-700 dark:text-gray-300">{{ $service->functionFreelancer->name }}</td>
                                <td class="px-4 py-4 text-gray-700 dark:text-gray-300">
                                    {{ $service->location ?? '—' }}
                                    @if(filled($service->description))
                                        <span class="block max-w-[16rem] truncate text-xs text-gray-400 dark:text-gray-500" title="{{ $service->description }}">{{ $service->description }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 text-gray-700 dark:text-gray-300 whitespace-nowrap">
                                    {{ $service->start_date->format('d/m/Y') }}
                                    <span class="text-gray-400 dark:text-gray-500">
                                        {{ substr($service->start_time, 0, 5) }}–{{ substr($service->end_time, 0, 5) }}
                                    </span>
                                    @if($service->start_date->ne($service->end_date))
                                        <span title="Termina no dia seguinte" class="text-amber-500">+1</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 text-gray-700 dark:text-gray-300">{{ $service->formattedDuration() }}</td>
                                <td class="px-4 py-4 text-gray-700 dark:text-gray-300">R$ {{ number_format($service->price, 2, ',', '.') }}</td>
                                <td class="px-4 py-4">
                                    <x-freelancer-signature-badge :service="$service" />
                                    {{-- O par aditivo/aditivado explica por que duas linhas do mesmo
                                         turno aparecem na lista e só uma delas será paga. --}}
                                    @if($service->isAmendment())
                                        <span class="block text-xs font-bold text-indigo-600 dark:text-indigo-400"
                                              title="Aditivo do contrato #{{ $service->parent_service_id }}">Aditivo</span>
                                    @elseif($service->isAmended())
                                        <span class="block text-xs font-bold text-gray-400 dark:text-gray-500"
                                              title="Assinado normalmente; o pagamento do turno é feito pelo aditivo #{{ $service->amendment_service_id }}">Aditivado · pago no aditivo</span>
                                    @endif
                                    {{-- Mesma informação das tarjas da tela do contrato, em selo. --}}
                                    @if($service->isSignedAfterStart())
                                        <span class="block mt-1 text-xs font-bold text-rose-600 dark:text-rose-400 whitespace-nowrap"
                                              title="O turno começou em {{ $service->startsAt()->format('d/m/Y H:i') }} e a assinatura do freelancer foi registrada em {{ $service->freelancer_signed_at->format('d/m/Y H:i') }}. Tolerância de {{ \App\Models\FreelancerService::SIGNATURE_TOLERANCE_MINUTES }} minutos.">
                                            🕒 Assinado {{ $service->formattedSignatureDelay() }} após o início
                                        </span>
                                    @elseif($service->isUnsignedAfterStart())
                                        <span class="block mt-1 text-xs font-bold text-rose-600 dark:text-rose-400 whitespace-nowrap"
                                              title="O turno começou em {{ $service->startsAt()->format('d/m/Y H:i') }} e o freelancer nunca assinou este contrato.">
                                            ⏳ Sem assinatura · turno começou há {{ $service->formattedTimeSinceStart() }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 text-right space-x-3 whitespace-nowrap">
                                    <a href="{{ route('freelancer-services.show', $service) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline font-medium text-xs">
                                        {{ $service->canBeUpdated() ? 'Editar' : 'Ver' }}
                                    </a>
                                    @if($service->canBeDeleted())
                                        <form method="POST" action="{{ route('freelancer-services.destroy', $service) }}" class="inline"
                                              onsubmit="return confirm('Excluir este serviço?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 dark:text-red-400 hover:underline font-medium text-xs">Excluir</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($services->hasPages())
                    <div class="px-5 py-4 border-t border-gray-100 dark:border-gray-700">
                        {{ $services->links() }}
                    </div>
                @endif
            @endif
        </div>
    </div>
</div>
</x-app-layout>
