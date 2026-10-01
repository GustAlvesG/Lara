<x-app-layout :bootstrap-grid="false">
    <x-slot name="css">
        <link rel="stylesheet" href="{{ asset('css/information/editor.css') }}">
        <link rel="stylesheet" href="{{ asset('css/information/form.css') }}">
    </x-slot>

    <x-page>
        <x-page-title title="Nova informação" :back="route('information.index')">
            Aparece para todos no InfoClube assim que for criada.
        </x-page-title>

        @include('information.partials.form', ['route' => route('information.store')])
    </x-page>

    <x-slot name="js">
        <script src="{{ asset('js/information/form.js') }}"></script>
        <script src="{{ asset('js/information/editor.js') }}"></script>
    </x-slot>
</x-app-layout>
