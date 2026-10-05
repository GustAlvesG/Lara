<x-app-layout>
<div class="py-6">
    {{-- Duas tabelas largas (rascunho + disponíveis): usa a largura disponível. --}}
    <div class="max-w-full mx-auto sm:px-6 lg:px-8" id="lotes">

        <div class="mb-8">
            <h1 class="font-display text-2xl font-semibold tracking-tight text-ink">Lotes de aprovação</h1>
            <p class="text-ink-2 font-medium">Monte um lote com os contratos já assinados pelas duas partes e envie para a gerência aprovar.</p>
        </div>

        @include('freelancer.services.partials.tabs')
        @include('partials.alerts')

        <x-search-bar mode="client" target="#lotes" placeholder="Buscar freelancer, CPF, evento ou lote" class="mb-6" />

        {{-- ============ RASCUNHO EM MONTAGEM ============ --}}
        <div class="bg-surface rounded-2xl shadow-card border border-line mb-8">
            <div class="px-6 py-5 border-b border-line flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-extrabold text-ink">Lote em montagem</h2>
                    <p class="text-sm text-ink-2">
                        @if($draft && $draft->services->count())
                            @php $comissoes = $draft->services->filter->isCommissionAmendment(); @endphp
                            {{ $draft->services->count() }} contrato(s) · total R$ {{ number_format($draft->services->sum('price'), 2, ',', '.') }}
                            @if($comissoes->isNotEmpty())
                                <span class="block text-ok font-semibold">
                                    Inclui {{ $comissoes->count() }} termo(s) de comissão de venda ·
                                    R$ {{ number_format($comissoes->sum('price'), 2, ',', '.') }}
                                </span>
                            @endif
                        @else
                            Nenhum contrato incluído ainda.
                        @endif
                    </p>
                </div>

                @if($draft && $draft->services->count())
                    <div class="flex items-center gap-3">
                        <form action="{{ route('freelancer-batches.discard') }}" method="POST"
                              onsubmit="return confirm('Descartar o rascunho? Os contratos voltam para a fila e nada é perdido.')">
                            @csrf @method('DELETE')
                            <button type="submit" class="px-4 py-2 rounded-xl text-sm font-bold text-ink-2 hover:bg-subtle transition">
                                Descartar rascunho
                            </button>
                        </form>
                        <form action="{{ route('freelancer-batches.send') }}" method="POST"
                              onsubmit="return confirm('Enviar o lote para a gerência? Depois de enviado ele não pode mais ser alterado.')">
                            @csrf
                            <button type="submit" class="px-5 py-2.5 rounded-xl text-sm font-bold text-white bg-grena hover:bg-[#7c0001] shadow-card transition">
                                Enviar para aprovação
                            </button>
                        </form>
                    </div>
                @endif
            </div>

            @if($draft && $draft->services->count())
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-line">
                        <thead class="bg-subtle">
                            <tr class="text-left text-xs font-bold uppercase tracking-wider text-ink-3">
                                <th class="px-4 py-3">Freelancer</th>
                                <th class="px-4 py-3">Função / Local</th>
                                <th class="px-4 py-3">Período</th>
                                <th class="px-4 py-3 text-right">Valor</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach($draft->services as $service)
                                <tr data-search="" class="text-sm">
                                    <td class="px-4 py-4 font-bold text-ink">
                                        <span class="font-mono text-xs font-normal text-ink-3">#{{ $service->id }}</span>
                                        {{ $service->freelancer->name ?? '—' }}
                                        <x-freelancer-kind-badge :service="$service" :note="true" class="mt-1" />
                                    </td>
                                    <td class="px-4 py-4 text-ink-2">
                                        {{ $service->functionFreelancer->name ?? '—' }}
                                        <span class="block text-xs text-ink-3">{{ $service->location }}</span>
                                    </td>
                                    <td class="px-4 py-4 text-ink-2 tabular-nums">{{ $service->formattedPeriod() }}</td>
                                    <td class="px-4 py-4 text-right font-bold text-ink tabular-nums">R$ {{ number_format($service->price, 2, ',', '.') }}</td>
                                    <td class="px-4 py-4 text-right">
                                        <form action="{{ route('freelancer-batches.items.remove', $service) }}" method="POST">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-xs font-bold text-ink-2 hover:text-grena-ink transition">Retirar</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="px-6 py-10 text-center text-ink-2">
                    Selecione contratos na lista abaixo para começar a montar o lote.
                </div>
            @endif
        </div>

        {{-- ============ CONTRATOS DISPONÍVEIS ============ --}}
        <div class="bg-surface rounded-2xl shadow-card border border-line mb-8">
            <div class="px-6 py-5 border-b border-line">
                <h2 class="text-lg font-extrabold text-ink">Contratos disponíveis</h2>
                {{-- A regra das 08h é a razão mais comum de um contrato assinado
                     não estar nesta lista; dizê-la aqui evita procurar defeito. --}}
                <p class="text-sm text-ink-2">
                    Assinados pelo freelancer e pelo coordenador, fora de qualquer lote em aberto.
                    Turnos de hoje entram na lista às {{ sprintf('%02dh', \App\Models\FreelancerService::RELEASE_HOUR) }}
                    de amanhã — até lá ainda cabe aditivo neles.
                </p>
            </div>

            @if($available->isEmpty())
                <div class="px-6 py-10 text-center text-ink-2">
                    Nenhum contrato disponível para lote no momento.
                </div>
            @else
                <form action="{{ route('freelancer-batches.items.add') }}" method="POST"
                      x-data="{ selected: [], all: @js($available->pluck('id')->map(fn($id) => (string) $id)->values()) }">
                    @csrf
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-line">
                            <thead class="bg-subtle">
                                <tr class="text-left text-xs font-bold uppercase tracking-wider text-ink-3">
                                    <th class="px-4 py-3 w-10">
                                        <input type="checkbox" class="rounded border-line-strong text-grena-ink focus:ring-grena-tint"
                                               :checked="selected.length === all.length && all.length > 0"
                                               @change="selected = $event.target.checked ? all.filter(id => { const b = $root.querySelector('input[name=\'services[]\'][value=\'' + id + '\']'); return b && b.closest('tr').style.display !== 'none'; }) : []">
                                    </th>
                                    <th class="px-4 py-3">Freelancer</th>
                                    <th class="px-4 py-3">Função / Local</th>
                                    <th class="px-4 py-3">Período</th>
                                    <th class="px-4 py-3 text-right">Valor</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                @foreach($available as $service)
                                    <tr data-search="" class="text-sm">
                                        <td class="px-4 py-4">
                                            <input type="checkbox" name="services[]" value="{{ $service->id }}" x-model="selected"
                                                   class="rounded border-line-strong text-grena-ink focus:ring-grena-tint">
                                        </td>
                                        <td class="px-4 py-4 font-bold text-ink">
                                            <span class="font-mono text-xs font-normal text-ink-3">#{{ $service->id }}</span>
                                            {{ $service->freelancer->name ?? '—' }}
                                            <x-freelancer-kind-badge :service="$service" :note="true" class="mt-1" />
                                            @if($service->isManagerRejected())
                                                <span class="block mt-1 text-xs font-bold text-warn">
                                                    Recusado pela gerência{{ $service->managerRejectedBy ? ' por ' . $service->managerRejectedBy->name : '' }}:
                                                    {{ $service->manager_rejection_reason ?: 'sem motivo informado' }}
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 text-ink-2">
                                            {{ $service->functionFreelancer->name ?? '—' }}
                                            <span class="block text-xs text-ink-3">{{ $service->location }}</span>
                                        </td>
                                        <td class="px-4 py-4 text-ink-2 tabular-nums">{{ $service->formattedPeriod() }}</td>
                                        <td class="px-4 py-4 text-right font-bold text-ink tabular-nums">R$ {{ number_format($service->price, 2, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="px-4 py-4 border-t border-line flex items-center justify-between gap-4">
                        <p class="text-sm text-ink-2">
                            <span x-text="selected.length"></span> selecionado(s)
                        </p>
                        <button type="submit" :disabled="selected.length === 0"
                                class="px-5 py-2.5 rounded-xl text-sm font-bold text-white bg-grena hover:bg-[#7c0001] shadow-card transition disabled:opacity-40 disabled:cursor-not-allowed">
                            Incluir no lote
                        </button>
                    </div>
                </form>
            @endif
        </div>

        {{-- ============ LOTES JÁ ENVIADOS ============ --}}
        <div class="bg-surface rounded-2xl shadow-card border border-line">
            <div class="px-6 py-5 border-b border-line">
                <h2 class="text-lg font-extrabold text-ink">Lotes enviados</h2>
            </div>

            @if($history->isEmpty())
                <div class="px-6 py-10 text-center text-ink-2">Você ainda não enviou nenhum lote.</div>
            @else
                <div class="divide-y divide-line">
                    @foreach($history as $batch)
                        <a data-search="" href="{{ route('freelancer-batches.show', $batch) }}"
                           class="flex items-center justify-between gap-4 px-4 py-4 hover:bg-subtle transition">
                            <div>
                                <p class="font-bold text-ink">Lote #{{ $batch->id }}</p>
                                <p class="text-xs text-ink-2">
                                    {{ $batch->services_count }} contrato(s) ·
                                    enviado em {{ $batch->sent_at?->format('d/m/Y H:i') ?? '—' }}
                                    @if($batch->isReviewed())
                                        · analisado por {{ $batch->reviewedBy->name ?? '—' }} em {{ $batch->reviewed_at?->format('d/m/Y H:i') }}
                                    @endif
                                </p>
                            </div>
                            {{-- Verde só quando a diretoria aprovou de fato; em
                                 trâmite fica âmbar, recusado/encerrado vermelho. --}}
                            @php
                                $tomLote = match (true) {
                                    $batch->isDirectorApproved() => 'bg-ok-soft text-ok',
                                    $batch->isDirectorRejected(), $batch->isClosed() => 'bg-danger-soft text-danger',
                                    default => 'bg-warn-soft text-warn',
                                };
                            @endphp
                            <span class="shrink-0 px-3 py-1 rounded-full text-xs font-bold {{ $tomLote }}">
                                {{ $batch->statusLabel() }}
                            </span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

    </div>
</div>
</x-app-layout>
