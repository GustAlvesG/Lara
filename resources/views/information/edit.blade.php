<x-app-layout :bootstrap-grid="false">
    <x-slot name="css">
        <link rel="stylesheet" href="{{ asset('css/information/editor.css') }}">
        <link rel="stylesheet" href="{{ asset('css/information/form.css') }}">
    </x-slot>

    <x-page>
        <x-page-title :title="'Editar: ' . $info->name" :back="route('information.show', $info->id)">
            Salvar cria uma nova versão — a atual fica preservada no histórico.

            <x-slot:actions>
                <x-secondary-button-a href="{{ route('information.history', $info->information_id) }}">
                    <x-icon name="history" /> Histórico
                </x-secondary-button-a>
            </x-slot:actions>
        </x-page-title>

        @include('information.partials.form', ['route' => route('information.store')])
    </x-page>

    <x-slot name="js">
        <script src="{{ asset('js/information/form.js') }}"></script>
        <script src="{{ asset('js/information/editor.js') }}"></script>
    </x-slot>
</x-app-layout>
