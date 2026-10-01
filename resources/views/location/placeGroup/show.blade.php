{{--
    Modalidade: dados do grupo (formulário), e as três listas que dependem
    dele — locais, torneios e regras de locação —, cada uma com busca na página.
--}}
@php
    $section = 'flex flex-col gap-4 rounded-card bg-surface p-5 shadow-card sm:p-6';
    $sectionTitle = 'font-display text-lg font-semibold tracking-tight text-ink';
@endphp
<x-app-layout>
    <x-slot name="css">
        <link rel="stylesheet" href="{{ asset('css/switch.css') }}">
    </x-slot>

    <x-page>
        <x-page-title :title="$item->name" :back="route('place-group.index')">
            Dados da modalidade, locais, torneios e regras de locação.
        </x-page-title>

        @include('partials.alerts')

        <section class="{{ $section }}">
            <h2 class="{{ $sectionTitle }}">Dados da modalidade</h2>
            <x-crud.create :route="route('place-group.update', $item->id)" :method="'POST'" :put="True">
                <x-slot name="formInputs">
                    @include('location.placeGroup.partials.form', ['item' => $item])
                </x-slot>
            </x-crud.create>
        </section>

        <section class="{{ $section }}">
            @include('location.placeGroup.partials.places', ['places' => $item->places])
        </section>

        <section class="{{ $section }}">
            @include('location.placeGroup.partials.tournaments', ['tournaments' => $item->tournaments])
        </section>

        <section class="{{ $section }}">
            @include('location.placeGroup.partials.place-group-rules')
        </section>
    </x-page>

    <x-slot name="js">
        <script src="{{ asset('js/image-preview/index.js') }}"></script>
        <script src="{{ asset('js/schedule/form-rules.js') }}"></script>
    </x-slot>
</x-app-layout>
