<x-app-layout>
<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8" id="fila-lotes">

        <div class="mb-8">
            <h1 class="font-display text-2xl font-semibold tracking-tight text-ink">Aprovação da gerência</h1>
            <p class="text-ink-2 font-medium">Lotes enviados pelos coordenadores, aguardando sua análise. Os mais antigos primeiro.</p>
        </div>

        @include('freelancer.services.partials.tabs')
        @include('partials.alerts')

        <x-search-bar mode="client" target="#fila-lotes" placeholder="Buscar lote ou quem montou" class="mb-6" />

        {{-- ============ AGUARDANDO O CÓDIGO DA DIRETORIA ============ --}}
        @if($awaitingDirector->isNotEmpty())
            <div class="bg-surface rounded-2xl shadow-card border-2 border-warn/40 mb-8 overflow-hidden">
                <div class="px-6 py-5 bg-warn-soft border-b border-warn/40">
                    <h2 class="text-lg font-extrabold text-ink">Aguardando a diretoria</h2>
                    <p class="text-sm text-ink-2">
                        Você já aprovou estes lotes. Peça o código ao diretor e registre a decisão dele.
                    </p>
                </div>
                <div class="divide-y divide-line">
                    @foreach($awaitingDirector as $batch)
                        <a data-search="" href="{{ route('freelancer-batches.show', $batch) }}"
                           class="flex items-center justify-between gap-6 px-6 py-5 hover:bg-subtle transition">
                            <div class="min-w-0">
                                <p class="font-extrabold text-ink">Lote #{{ $batch->id }}</p>
                                <p class="text-sm text-ink-2">
                                    {{ $batch->createdBy->name ?? 'Coordenador' }} ·
                                    aprovado por você em {{ $batch->reviewed_at?->format('d/m/Y H:i') ?? '—' }}
                                </p>
                                @if($batch->commissions_count)
                                    <p class="mt-1 text-xs font-bold text-ok">
                                        Inclui {{ $batch->commissions_count }} termo(s) de comissão de venda
                                    </p>
                                @endif
                            </div>
                            <div class="flex items-center gap-6 shrink-0">
                                <div class="text-right">
                                    <p class="text-xs font-bold uppercase tracking-wider text-ink-3">Contratos</p>
                                    <p class="text-lg font-extrabold text-ink tabular-nums">{{ $batch->services_count }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs font-bold uppercase tracking-wider text-ink-3">Total</p>
                                    <p class="text-lg font-extrabold text-ink tabular-nums">
                                        R$ {{ number_format($batch->services_sum_price ?? 0, 2, ',', '.') }}
                                    </p>
                                </div>
                                <span class="px-4 py-2 rounded-xl text-sm font-bold
                                    {{ $batch->director_notified_at
                                        ? 'bg-warn-soft text-warn'
                                        : 'text-white bg-grena' }}">
                                    {{ $batch->director_notified_at ? 'Informar código' : 'Enviar e-mail' }}
                                </span>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="bg-surface rounded-2xl shadow-card border border-line">
            @if($batches->isEmpty())
                <div class="px-6 py-16 text-center">
                    <p class="text-lg font-bold text-ink">Nenhum lote aguardando aprovação</p>
                    <p class="text-sm text-ink-2 mt-1">Assim que um coordenador enviar um lote, ele aparece aqui.</p>
                </div>
            @else
                <div class="divide-y divide-line">
                    @foreach($batches as $batch)
                        <a data-search="" href="{{ route('freelancer-batches.show', $batch) }}"
                           class="flex items-center justify-between gap-6 px-6 py-5 hover:bg-subtle transition">
                            <div class="min-w-0">
                                <p class="font-extrabold text-ink">Lote #{{ $batch->id }}</p>
                                <p class="text-sm text-ink-2">
                                    {{ $batch->createdBy->name ?? 'Coordenador' }} ·
                                    enviado em {{ $batch->sent_at?->format('d/m/Y H:i') ?? '—' }}
                                </p>
                                @if($batch->commissions_count)
                                    <p class="mt-1 text-xs font-bold text-ok">
                                        Inclui {{ $batch->commissions_count }} termo(s) de comissão de venda
                                    </p>
                                @endif
                            </div>
                            <div class="flex items-center gap-6 shrink-0">
                                <div class="text-right">
                                    <p class="text-xs font-bold uppercase tracking-wider text-ink-3">Contratos</p>
                                    <p class="text-lg font-extrabold text-ink tabular-nums">{{ $batch->services_count }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs font-bold uppercase tracking-wider text-ink-3">Total</p>
                                    <p class="text-lg font-extrabold text-ink tabular-nums">
                                        R$ {{ number_format($batch->services_sum_price ?? 0, 2, ',', '.') }}
                                    </p>
                                </div>
                                <span class="px-4 py-2 rounded-xl text-sm font-bold text-white bg-grena">Analisar</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

    </div>
</div>
</x-app-layout>
