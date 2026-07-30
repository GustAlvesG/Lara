<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Editar: {{ $info->name }}
                </h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Salvar cria uma nova versão — a atual fica preservada no histórico.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <x-secondary-button-a href="{{ route('information.history', $info->information_id) }}">
                    Histórico
                </x-secondary-button-a>
                <x-secondary-button-a href="{{ route('information.show', $info->id) }}">
                    Cancelar
                </x-secondary-button-a>
            </div>
        </div>
    </x-slot>

    <x-slot name="css">
        <link rel="stylesheet" href="{{ asset('css/information/editor.css') }}">
        <link rel="stylesheet" href="{{ asset('css/information/form.css') }}">
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            @include('information.partials.form', ['route' => route('information.store')])
        </div>
    </div>

    <x-slot name="js">
        <script src="{{ asset('js/information/form.js') }}"></script>
        <script src="{{ asset('js/information/editor.js') }}"></script>
    </x-slot>
</x-app-layout>
