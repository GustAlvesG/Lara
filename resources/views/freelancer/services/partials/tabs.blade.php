{{--
    Abas de Serviços / Contratos: Contratos, Lotes, Aprovação e Financeiro.

    Cada aba aparece só para quem a opera, e a condição aqui é a **mesma** que a
    rota exige — sem isso a aba viraria um link para um 403. As três primeiras
    estão sob `permission:manage freelancers`; o Financeiro tem regra própria
    (Gate `manage-freelancer-payments`: setor Contabilidade ou Gerência), então
    quem só tem ela navega entre as abas que alcança sem esbarrar nas outras.

    Aceita `$activeTab` (nome de rota) para telas que não são a aba em si —
    a de um lote, por exemplo, que continua destacando "Lotes".
--}}
@php
    $user = auth()->user();
    $canFreelancers = $user?->can('manage freelancers') ?? false;

    $tabs = [];

    if ($canFreelancers) {
        $tabs[] = [
            'route' => 'freelancer-services.index',
            'label' => 'Contratos',
            'matches' => ['freelancer-services.index', 'freelancer-services.create', 'freelancer-services.bulk', 'freelancer-services.show'],
        ];
    }

    // Montar lote é atribuição de coordenador de setor.
    if ($canFreelancers && $user?->isCoordinator()) {
        $tabs[] = [
            'route' => 'freelancer-batches.index',
            'label' => 'Lotes',
            'matches' => ['freelancer-batches.index', 'freelancer-batches.show'],
        ];
    }

    // Aprovação: só o coordenador do setor Gerência.
    if ($canFreelancers && $user?->isManagementCoordinator()) {
        $tabs[] = [
            'route' => 'freelancer-batches.queue',
            'label' => 'Aprovação',
            'matches' => ['freelancer-batches.queue'],
        ];
    }

    if ($user?->can('manage-freelancer-payments')) {
        $tabs[] = [
            'route' => 'freelancer-services.finance',
            'label' => 'Financeiro',
            // A aba cobre as telas de lote, avulsos e lista plana.
            'matches' => ['freelancer-services.finance', 'freelancer-services.finance.*'],
        ];
    }

    $activeTab = $activeTab ?? null;
@endphp

@if(count($tabs) > 1)
<div class="mb-6 border-b border-gray-200 dark:border-gray-700">
    <nav class="-mb-px flex gap-6 overflow-x-auto">
        @foreach($tabs as $tab)
            @php
                $active = $activeTab
                    ? $activeTab === $tab['route']
                    : request()->routeIs($tab['matches']);
            @endphp
            <a href="{{ route($tab['route']) }}"
               @if($active) aria-current="page" @endif
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
