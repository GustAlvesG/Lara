@php
    /**
     * Reconferência da divergência — a segunda aprovação, que só existe quando
     * o horário informado pelo funcionário ficou diferente do previsto.
     * Coordenador primeiro (ele sabe o que foi combinado no setor), gerência
     * depois.
     */
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Reconferência de Cachê') }}
        </h2>
    </x-slot>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

        @include('employee.cache.partials.tabs')
        @include('partials.alerts')

        <div>
            <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white leading-tight">Horários divergentes</h1>
            <p class="text-gray-500 dark:text-gray-400 font-medium">
                Cachês em que o horário informado na assinatura ficou diferente do que a gerência aprovou.
                Sem divergência, o cachê vai direto ao financeiro e não aparece aqui.
            </p>
        </div>

        @include('employee.cache.partials.recheck-table', [
            'title' => 'Aguardando você (coordenador)',
            'caches' => $coordinatorQueue,
            'stage' => 'coordinator',
            'empty' => 'Nenhum cachê aguardando a sua reconferência.',
        ])

        @if($isManager)
            @include('employee.cache.partials.recheck-table', [
                'title' => 'Aguardando a gerência',
                'caches' => $managerQueue,
                'stage' => 'manager',
                'empty' => 'Nenhum cachê aguardando a reconferência da gerência.',
            ])
        @endif
    </div>
</div>
</x-app-layout>
