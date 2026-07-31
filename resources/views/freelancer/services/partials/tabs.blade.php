{{-- Abas de Serviços / Contratos. Cada aba aparece só para quem a opera. --}}
@php
    $user = auth()->user();

    $tabs = [
        ['route' => 'freelancer-services.index', 'label' => 'Contratos'],
    ];

    // Montar lote é atribuição de coordenador de setor.
    if ($user?->isCoordinator()) {
        $tabs[] = ['route' => 'freelancer-batches.index', 'label' => 'Lotes'];
    }

    // Aprovação: só o coordenador do setor Gerência.
    if ($user?->isManagementCoordinator()) {
        $tabs[] = ['route' => 'freelancer-batches.queue', 'label' => 'Aprovação'];
    }

    if ($user?->can('manage freelancer payments')) {
        $tabs[] = ['route' => 'freelancer-services.finance', 'label' => 'Financeiro'];
    }
@endphp

@if(count($tabs) > 1)
<div class="mb-6 border-b border-gray-200 dark:border-gray-700">
    <nav class="-mb-px flex gap-6">
        @foreach($tabs as $tab)
            @php $active = request()->routeIs($tab['route']); @endphp
            <a href="{{ route($tab['route']) }}"
               class="whitespace-nowrap border-b-2 px-1 py-3 text-sm font-bold transition
                      {{ $active
                          ? 'border-[#A00001] text-[#A00001] dark:text-red-400 dark:border-red-400'
                          : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:border-gray-300' }}">
                {{ $tab['label'] }}
            </a>
        @endforeach
    </nav>
</div>
@endif
