{{--
    Acompanhamento do trâmite — a tela do setor Comercial (Gate
    `track-freelancer-batches`). SÓ LEITURA: aprovar é da Gerência, pagar é do
    Financeiro, e nenhuma das duas ações existe aqui.

    A pergunta que a tela responde é "onde parou o contrato de fulano?", e a
    resposta tem quatro etapas: assinaturas → gerência → diretoria → pagamento.
    Por isso o lote aparece com a linha do tempo inteira, e não só com o rótulo
    do estado atual: o Comercial precisa saber o que já passou para dizer ao
    freelancer o que falta.
--}}
@php
    $user = auth()->user();

    // O Comercial nem sempre tem `manage freelancers`, e a tela do lote ainda
    // exige ser o coordenador que o montou ou a Gerência. Sem essas duas
    // conferências, os links daqui virariam 403 — pior que não ter link.
    $canOpenServices = $user?->can('manage freelancers') ?? false;
    // Resolvido uma vez: dentro do laço, cada chamada seria uma consulta a
    // user_sector por lote listado.
    $isManager = $canOpenServices && ($user?->isManagementCoordinator() ?? false);
    $canOpenBatch = fn($batch) => $canOpenServices && ($isManager || $batch->created_by === $user?->id);

    $brl = fn($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');

    $stepStyles = [
        'done' => ['bg-emerald-500 text-white', 'text-gray-900 dark:text-white'],
        'current' => ['bg-amber-500 text-white', 'text-amber-700 dark:text-amber-300'],
        'rejected' => ['bg-red-600 text-white', 'text-red-700 dark:text-red-300'],
        'pending' => ['bg-gray-200 text-gray-500 dark:bg-gray-600 dark:text-gray-300', 'text-gray-400 dark:text-gray-500'],
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Serviços / Contratos') }}
        </h2>
    </x-slot>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        <div class="mb-8">
            <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white leading-tight">Acompanhamento</h1>
            <p class="text-gray-500 dark:text-gray-400 font-medium">
                Onde cada contrato parou: assinaturas, gerência, diretoria e pagamento. Tela de consulta — as
                aprovações e as baixas continuam com a Gerência e o Financeiro.
            </p>
        </div>

        @include('freelancer.services.partials.tabs', ['activeTab' => 'freelancer-services.tracking'])
        @include('partials.alerts')

        {{-- ============ FILTRO DE PERÍODO ============ --}}
        <div class="mb-6 flex flex-wrap items-center gap-2">
            <span class="text-xs font-bold uppercase tracking-wider text-gray-400 mr-1">Período</span>
            @foreach($periods as $value => $label)
                <a href="{{ route('freelancer-services.tracking', ['periodo' => $value]) }}"
                   class="px-3 py-1.5 rounded-lg text-sm font-bold transition
                          {{ $period === $value
                              ? 'bg-[#A00001] text-white'
                              : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 border border-gray-200 dark:border-gray-700 hover:border-gray-300' }}">
                    {{ $label }}
                </a>
            @endforeach
            <span class="text-xs text-gray-400 dark:text-gray-500 ml-1">
                As filas em aberto aparecem sempre, mesmo fora do período — é o atraso que interessa achar.
            </span>
        </div>

        {{-- ============ RESUMO POR ETAPA ============ --}}
        <div class="grid grid-cols-2 md:grid-cols-4 xl:grid-cols-7 gap-4 mb-8">
            @foreach($summary as $item)
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700 p-4">
                    <p class="text-xs font-bold uppercase tracking-wider text-gray-400 leading-tight min-h-[2rem]">
                        {{ $item['label'] }}
                    </p>
                    <p class="mt-1 text-2xl font-extrabold text-gray-900 dark:text-white tabular-nums">{{ $item['count'] }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 tabular-nums">{{ $brl($item['total']) }}</p>
                </div>
            @endforeach
        </div>

        {{-- ============ LOTES ============ --}}
        <div class="mb-4 flex items-baseline justify-between gap-4">
            <h2 class="text-xl font-extrabold text-gray-900 dark:text-white">Lotes</h2>
            <span class="text-xs text-gray-400 dark:text-gray-500">
                Toque em um lote para ver os contratos dele
            </span>
        </div>

        <div class="space-y-4 mb-10">
            @forelse($batches as $batch)
                <div x-data="{ open: false }"
                     class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700 overflow-hidden">

                    {{-- Cabeçalho clicável em <div>, não em <button>: a linha do
                         tempo abaixo é conteúdo de bloco, que um botão não pode
                         conter. O papel e o teclado são declarados à mão. --}}
                    <div role="button" tabindex="0" :aria-expanded="open ? 'true' : 'false'"
                         @click="open = !open" @keydown.enter="open = !open" @keydown.space.prevent="open = !open"
                         class="w-full text-left px-6 py-5 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/40 transition">
                        <div class="flex flex-wrap items-center justify-between gap-4">
                            <div class="min-w-0">
                                <div class="flex items-center gap-3">
                                    <p class="font-extrabold text-gray-900 dark:text-white">Lote #{{ $batch->id }}</p>
                                    @include('freelancer.services.partials.stage-badge', [
                                        'stage' => $batch->trackingStage(),
                                        'label' => $batch->trackingStageLabel(),
                                    ])
                                </div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    Montado por {{ $batch->createdBy->name ?? '—' }}
                                    @if($batch->sent_at) · enviado em {{ $batch->sent_at->format('d/m/Y H:i') }} @endif
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
                                        {{ $brl($batch->services_sum_price ?? 0) }}
                                    </p>
                                </div>
                                <svg class="w-5 h-5 text-gray-400 transition-transform" :class="open && 'rotate-180'"
                                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </div>
                        </div>

                        {{-- Linha do tempo das quatro etapas --}}
                        <ol class="mt-4 flex flex-wrap items-center gap-x-3 gap-y-3">
                            @foreach($batch->trackingSteps() as $step)
                                @php([$bolinha, $texto] = $stepStyles[$step['state']] ?? $stepStyles['pending'])
                                <li class="flex items-center gap-2">
                                    <span class="w-6 h-6 rounded-full grid place-items-center text-xs font-extrabold shrink-0 {{ $bolinha }}">
                                        @if($step['state'] === 'done')
                                            ✓
                                        @elseif($step['state'] === 'rejected')
                                            ✕
                                        @else
                                            {{ $loop->iteration }}
                                        @endif
                                    </span>
                                    <span class="leading-tight">
                                        <span class="block text-xs font-bold {{ $texto }}">{{ $step['label'] }}</span>
                                        @if($step['detail'])
                                            <span class="block text-[11px] text-gray-400 dark:text-gray-500">{{ $step['detail'] }}</span>
                                        @endif
                                    </span>
                                </li>
                                @if(!$loop->last)
                                    <span class="hidden sm:block h-px w-6 bg-gray-200 dark:bg-gray-600"></span>
                                @endif
                            @endforeach
                        </ol>
                    </div>

                    {{-- Contratos do lote --}}
                    <div x-show="open" x-cloak class="border-t border-gray-100 dark:border-gray-700">
                        @if($canOpenBatch($batch))
                            <div class="px-6 pt-4">
                                <a href="{{ route('freelancer-batches.show', $batch) }}"
                                   class="text-sm font-bold text-indigo-600 dark:text-indigo-400 hover:underline">
                                    Abrir o lote →
                                </a>
                            </div>
                        @endif
                        @include('freelancer.services.partials.tracking-services', [
                            'services' => $batch->services,
                            'canOpenServices' => $canOpenServices,
                            'showBatch' => false,
                        ])
                    </div>
                </div>
            @empty
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700 px-6 py-16 text-center">
                    <p class="text-lg font-bold text-gray-900 dark:text-white">Nenhum lote no período</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Assim que um coordenador montar um lote, ele aparece aqui com o andamento.
                    </p>
                </div>
            @endforelse

            @if($batches->count() >= $batchLimit)
                <p class="text-xs text-gray-400 dark:text-gray-500 text-center">
                    Mostrando os {{ $batchLimit }} lotes mais recentes. Reduza o período para ver menos.
                </p>
            @endif
        </div>

        {{-- ============ FORA DE LOTE ============ --}}
        <div class="mb-4 flex items-baseline justify-between gap-4">
            <h2 class="text-xl font-extrabold text-gray-900 dark:text-white">Ainda fora de lote</h2>
            <span class="text-xs text-gray-400 dark:text-gray-500">
                Contratos que esperam assinatura ou o coordenador montar o lote
            </span>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700 overflow-hidden">
            @if($loose->isEmpty())
                <div class="px-6 py-16 text-center">
                    <p class="text-lg font-bold text-gray-900 dark:text-white">Nada parado fora de lote</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Todo contrato do período já está num lote em trâmite.
                    </p>
                </div>
            @else
                @include('freelancer.services.partials.tracking-services', [
                    'services' => $loose,
                    'canOpenServices' => $canOpenServices,
                    'showBatch' => true,
                ])
            @endif
        </div>

        @if($loose->count() >= $looseLimit)
            <p class="mt-3 text-xs text-gray-400 dark:text-gray-500 text-center">
                Lista limitada a {{ $looseLimit }} contratos por etapa. Reduza o período para ver menos.
            </p>
        @endif

    </div>
</div>
</x-app-layout>
