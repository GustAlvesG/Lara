{{--
    Abas de Serviços / Contratos: Contratos, Validação, Lotes, Aprovação,
    Diretoria, Acompanhamento e Financeiro.

    Cada aba aparece só para quem a opera, e a condição aqui é a **mesma** que a
    rota exige — sem isso a aba viraria um link para um 403. Contratos pede a
    lista de serviços; Validação, Lotes, Aprovação e Diretoria pedem
    `freelancers.servicos.gerenciar` MAIS o cargo (coordenador do Comercial,
    coordenador de setor, coordenador da Gerência); Acompanhamento e Financeiro
    têm permissão própria, e quem só tem uma delas navega sem esbarrar nas
    outras.

    Aceita `$activeTab` (nome de rota) para telas que não são a aba em si —
    a de um lote, por exemplo, que continua destacando "Lotes".
--}}
@php
    $P = \App\Authorization\Permissions::class;
    $user = auth()->user();
    $canManage = $user?->can($P::FREELANCERS_SERVICOS_GERENCIAR) ?? false;

    $tabs = [];

    if ($user?->can($P::FREELANCERS_SERVICOS_LISTAR)) {
        $tabs[] = [
            'route' => 'freelancer-services.index',
            'label' => 'Contratos',
            'matches' => ['freelancer-services.index', 'freelancer-services.create', 'freelancer-services.bulk', 'freelancer-services.show'],
        ];
    }

    // Validação dos contratos da redação 2: coordenador do Comercial. Vem antes
    // de Lotes porque é o passo anterior — só o validado entra em lote.
    if ($canManage && $user?->can('validate-freelancer-contracts')) {
        $tabs[] = [
            'route' => 'freelancer-validation.index',
            'label' => 'Validação',
            'matches' => ['freelancer-validation.*'],
        ];
    }

    // Montar lote é atribuição de coordenador de setor.
    if ($canManage && $user?->isCoordinator()) {
        $tabs[] = [
            'route' => 'freelancer-batches.index',
            'label' => 'Lotes',
            'matches' => ['freelancer-batches.index', 'freelancer-batches.show'],
        ];
    }

    // Aprovação: só o coordenador do setor Gerência.
    if ($canManage && $user?->isManagementCoordinator()) {
        $tabs[] = [
            'route' => 'freelancer-batches.queue',
            'label' => 'Aprovação',
            'matches' => ['freelancer-batches.queue'],
        ];
    }

    // Diretoria: cadastro de quem recebe os códigos e assina os contratos da
    // redação 2. Também só o coordenador da Gerência.
    if ($canManage && $user?->can('manage-freelancer-director')) {
        $tabs[] = [
            'route' => 'freelancer-director.edit',
            'label' => 'Diretoria',
            'matches' => ['freelancer-director.*'],
        ];
    }

    if ($user?->can($P::FREELANCERS_ACOMPANHAMENTO)) {
        $tabs[] = [
            'route' => 'freelancer-services.tracking',
            'label' => 'Acompanhamento',
            'matches' => ['freelancer-services.tracking'],
        ];
    }

    if ($user?->can($P::FREELANCERS_FINANCEIRO)) {
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
