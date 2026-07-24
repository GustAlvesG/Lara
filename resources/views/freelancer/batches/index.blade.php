<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Serviços / Contratos') }}
        </h2>
    </x-slot>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    {{-- Duas tabelas largas (rascunho + disponíveis): usa a largura disponível. --}}
    <div class="max-w-full mx-auto sm:px-6 lg:px-8">

        <div class="mb-8">
            <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white leading-tight">Lotes de aprovação</h1>
            <p class="text-gray-500 dark:text-gray-400 font-medium">Monte um lote com os contratos já assinados pelas duas partes e envie para a gerência aprovar.</p>
        </div>

        @include('freelancer.services.partials.tabs')
        @include('partials.alerts')

        {{-- ============ RASCUNHO EM MONTAGEM ============ --}}
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700 mb-8">
            <div class="px-6 py-5 border-b border-gray-100 dark:border-gray-700 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-extrabold text-gray-900 dark:text-white">Lote em montagem</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        @if($draft && $draft->services->count())
                            {{ $draft->services->count() }} contrato(s) · total R$ {{ number_format($draft->services->sum('price'), 2, ',', '.') }}
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
                            <button type="submit" class="px-4 py-2 rounded-xl text-sm font-bold text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                                Descartar rascunho
                            </button>
                        </form>
                        <form action="{{ route('freelancer-batches.send') }}" method="POST"
                              onsubmit="return confirm('Enviar o lote para a gerência? Depois de enviado ele não pode mais ser alterado.')">
                            @csrf
                            <button type="submit" class="px-5 py-2.5 rounded-xl text-sm font-bold text-white bg-[#A00001] hover:bg-[#7c0001] shadow transition">
                                Enviar para aprovação
                            </button>
                        </form>
                    </div>
                @endif
            </div>

            @if($draft && $draft->services->count())
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/40">
                            <tr class="text-left text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                <th class="px-4 py-3">Freelancer</th>
                                <th class="px-4 py-3">Função / Local</th>
                                <th class="px-4 py-3">Período</th>
                                <th class="px-4 py-3 text-right">Valor</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach($draft->services as $service)
                                <tr class="text-sm">
                                    <td class="px-4 py-4 font-bold text-gray-900 dark:text-white">{{ $service->freelancer->name ?? '—' }}</td>
                                    <td class="px-4 py-4 text-gray-600 dark:text-gray-300">
                                        {{ $service->functionFreelancer->name ?? '—' }}
                                        <span class="block text-xs text-gray-400">{{ $service->location }}</span>
                                    </td>
                                    <td class="px-4 py-4 text-gray-600 dark:text-gray-300 tabular-nums">{{ $service->formattedPeriod() }}</td>
                                    <td class="px-4 py-4 text-right font-bold text-gray-900 dark:text-white tabular-nums">R$ {{ number_format($service->price, 2, ',', '.') }}</td>
                                    <td class="px-4 py-4 text-right">
                                        <form action="{{ route('freelancer-batches.items.remove', $service) }}" method="POST">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-xs font-bold text-gray-500 hover:text-[#A00001] dark:text-gray-400 transition">Retirar</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="px-6 py-10 text-center text-gray-500 dark:text-gray-400">
                    Selecione contratos na lista abaixo para começar a montar o lote.
                </div>
            @endif
        </div>

        {{-- ============ CONTRATOS DISPONÍVEIS ============ --}}
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700 mb-8">
            <div class="px-6 py-5 border-b border-gray-100 dark:border-gray-700">
                <h2 class="text-lg font-extrabold text-gray-900 dark:text-white">Contratos disponíveis</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">Assinados pelo freelancer e pelo coordenador, fora de qualquer lote em aberto.</p>
            </div>

            @if($available->isEmpty())
                <div class="px-6 py-10 text-center text-gray-500 dark:text-gray-400">
                    Nenhum contrato disponível para lote no momento.
                </div>
            @else
                <form action="{{ route('freelancer-batches.items.add') }}" method="POST"
                      x-data="{ selected: [], all: @js($available->pluck('id')->map(fn($id) => (string) $id)->values()) }">
                    @csrf
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900/40">
                                <tr class="text-left text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                    <th class="px-4 py-3 w-10">
                                        <input type="checkbox" class="rounded border-gray-300 text-[#A00001] focus:ring-[#A00001]"
                                               :checked="selected.length === all.length && all.length > 0"
                                               @change="selected = $event.target.checked ? [...all] : []">
                                    </th>
                                    <th class="px-4 py-3">Freelancer</th>
                                    <th class="px-4 py-3">Função / Local</th>
                                    <th class="px-4 py-3">Período</th>
                                    <th class="px-4 py-3 text-right">Valor</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach($available as $service)
                                    <tr class="text-sm">
                                        <td class="px-4 py-4">
                                            <input type="checkbox" name="services[]" value="{{ $service->id }}" x-model="selected"
                                                   class="rounded border-gray-300 text-[#A00001] focus:ring-[#A00001]">
                                        </td>
                                        <td class="px-4 py-4 font-bold text-gray-900 dark:text-white">
                                            {{ $service->freelancer->name ?? '—' }}
                                            @if($service->isManagerRejected())
                                                <span class="block mt-1 text-xs font-bold text-amber-600 dark:text-amber-400">
                                                    Recusado pela gerência{{ $service->managerRejectedBy ? ' por ' . $service->managerRejectedBy->name : '' }}:
                                                    {{ $service->manager_rejection_reason ?: 'sem motivo informado' }}
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 text-gray-600 dark:text-gray-300">
                                            {{ $service->functionFreelancer->name ?? '—' }}
                                            <span class="block text-xs text-gray-400">{{ $service->location }}</span>
                                        </td>
                                        <td class="px-4 py-4 text-gray-600 dark:text-gray-300 tabular-nums">{{ $service->formattedPeriod() }}</td>
                                        <td class="px-4 py-4 text-right font-bold text-gray-900 dark:text-white tabular-nums">R$ {{ number_format($service->price, 2, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="px-4 py-4 border-t border-gray-100 dark:border-gray-700 flex items-center justify-between gap-4">
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            <span x-text="selected.length"></span> selecionado(s)
                        </p>
                        <button type="submit" :disabled="selected.length === 0"
                                class="px-5 py-2.5 rounded-xl text-sm font-bold text-white bg-[#A00001] hover:bg-[#7c0001] shadow transition disabled:opacity-40 disabled:cursor-not-allowed">
                            Incluir no lote
                        </button>
                    </div>
                </form>
            @endif
        </div>

        {{-- ============ LOTES JÁ ENVIADOS ============ --}}
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700">
            <div class="px-6 py-5 border-b border-gray-100 dark:border-gray-700">
                <h2 class="text-lg font-extrabold text-gray-900 dark:text-white">Lotes enviados</h2>
            </div>

            @if($history->isEmpty())
                <div class="px-6 py-10 text-center text-gray-500 dark:text-gray-400">Você ainda não enviou nenhum lote.</div>
            @else
                <div class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach($history as $batch)
                        <a href="{{ route('freelancer-batches.show', $batch) }}"
                           class="flex items-center justify-between gap-4 px-4 py-4 hover:bg-gray-50 dark:hover:bg-gray-700/40 transition">
                            <div>
                                <p class="font-bold text-gray-900 dark:text-white">Lote #{{ $batch->id }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
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
                                    $batch->isDirectorApproved() => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
                                    $batch->isDirectorRejected(), $batch->isClosed() => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
                                    default => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
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
