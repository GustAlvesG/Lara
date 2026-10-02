{{-- Modalidades (grupos de espaços) em cartões. A lista vem inteira: a busca filtra na página. --}}
<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title title="Modalidades" :back="route('schedule.index')">
            Grupos de espaços reserváveis, com seus locais, regras e preços.
            <x-slot:actions>
                <x-primary-button-a href="{{ route('place-group.create') }}"><x-icon name="plus" /> Nova modalidade</x-primary-button-a>
            </x-slot:actions>
        </x-page-title>

        @include('partials.alerts')

        @if ($groups->isEmpty())
            <x-empty-state icon="calendar">
                Nenhuma modalidade cadastrada.
                <a href="{{ route('place-group.create') }}" class="font-bold text-grena-ink hover:underline">Cadastrar a primeira</a>.
            </x-empty-state>
        @else
            <x-search-bar mode="client" target="#modalidades" placeholder="Buscar modalidade ou categoria" />

            <div id="modalidades" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($groups as $item)
                    @include('location.placeGroup.partials.element', ['item' => $item])
                @endforeach
            </div>
        @endif
    </x-page>
</x-app-layout>
