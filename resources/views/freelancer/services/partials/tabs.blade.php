{{-- Abas de Serviços / Contratos. A aba Financeiro só aparece para quem tem a permissão. --}}
@php
    $tabs = [
        ['route' => 'freelancer-services.index', 'label' => 'Contratos'],
    ];

    if (auth()->user()?->can('manage freelancer payments')) {
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
