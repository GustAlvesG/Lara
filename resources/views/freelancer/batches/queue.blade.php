<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Serviços / Contratos') }}
        </h2>
    </x-slot>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        <div class="mb-8">
            <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white leading-tight">Aprovação da gerência</h1>
            <p class="text-gray-500 dark:text-gray-400 font-medium">Lotes enviados pelos coordenadores, aguardando sua análise. Os mais antigos primeiro.</p>
        </div>

        @include('freelancer.services.partials.tabs')
        @include('partials.alerts')

        {{-- ============ AGUARDANDO O CÓDIGO DA DIRETORIA ============ --}}
        @if($awaitingDirector->isNotEmpty())
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border-2 border-amber-300 dark:border-amber-700 mb-8 overflow-hidden">
                <div class="px-6 py-5 bg-amber-50 dark:bg-amber-900/20 border-b border-amber-200 dark:border-amber-800">
                    <h2 class="text-lg font-extrabold text-gray-900 dark:text-white">Aguardando a diretoria</h2>
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        Você já aprovou estes lotes. Peça o código ao diretor e registre a decisão dele.
                    </p>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach($awaitingDirector as $batch)
                        <a href="{{ route('freelancer-batches.show', $batch) }}"
                           class="flex items-center justify-between gap-6 px-6 py-5 hover:bg-gray-50 dark:hover:bg-gray-700/40 transition">
                            <div class="min-w-0">
                                <p class="font-extrabold text-gray-900 dark:text-white">Lote #{{ $batch->id }}</p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ $batch->createdBy->name ?? 'Coordenador' }} ·
                                    aprovado por você em {{ $batch->reviewed_at?->format('d/m/Y H:i') ?? '—' }}
                                </p>
                            </div>
                            <div class="flex items-center gap-6 shrink-0">
                                <div class="text-right">
                                    <p class="text-xs font-bold uppercase tracking-wider text-gray-400">Contratos</p>
                                    <p class="text-lg font-extrabold text-gray-900 dark:text-white tabular-nums">{{ $batch->services_count }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs font-bold uppercase tracking-wider text-gray-400">Total</p>
                                    <p class="text-lg font-extrabold text-gray-900 dark:text-white tabular-nums">
                                        R$ {{ number_format($batch->services_sum_price ?? 0, 2, ',', '.') }}
                                    </p>
                                </div>
                                <span class="px-4 py-2 rounded-xl text-sm font-bold
                                    {{ $batch->director_notified_at
                                        ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300'
                                        : 'text-white bg-[#A00001]' }}">
                                    {{ $batch->director_notified_at ? 'Informar código' : 'Enviar e-mail' }}
                                </span>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700">
            @if($batches->isEmpty())
                <div class="px-6 py-16 text-center">
                    <p class="text-lg font-bold text-gray-900 dark:text-white">Nenhum lote aguardando aprovação</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Assim que um coordenador enviar um lote, ele aparece aqui.</p>
                </div>
            @else
                <div class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach($batches as $batch)
                        <a href="{{ route('freelancer-batches.show', $batch) }}"
                           class="flex items-center justify-between gap-6 px-6 py-5 hover:bg-gray-50 dark:hover:bg-gray-700/40 transition">
                            <div class="min-w-0">
                                <p class="font-extrabold text-gray-900 dark:text-white">Lote #{{ $batch->id }}</p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ $batch->createdBy->name ?? 'Coordenador' }} ·
                                    enviado em {{ $batch->sent_at?->format('d/m/Y H:i') ?? '—' }}
                                </p>
                            </div>
                            <div class="flex items-center gap-6 shrink-0">
                                <div class="text-right">
                                    <p class="text-xs font-bold uppercase tracking-wider text-gray-400">Contratos</p>
                                    <p class="text-lg font-extrabold text-gray-900 dark:text-white tabular-nums">{{ $batch->services_count }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs font-bold uppercase tracking-wider text-gray-400">Total</p>
                                    <p class="text-lg font-extrabold text-gray-900 dark:text-white tabular-nums">
                                        R$ {{ number_format($batch->services_sum_price ?? 0, 2, ',', '.') }}
                                    </p>
                                </div>
                                <span class="px-4 py-2 rounded-xl text-sm font-bold text-white bg-[#A00001]">Analisar</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

    </div>
</div>
</x-app-layout>
