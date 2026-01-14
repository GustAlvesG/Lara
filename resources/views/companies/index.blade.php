<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
        <div class="div">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Parceiros Terceirizados
            </h2>
        </div>
        
        
        @include('partials.crud.search-create', [
            'buttonCreateText' => 'Novo',
            'routeCreate' => route('company.create')
        ])

    </x-slot>

    <x-slot name="css">
    </x-slot>

    <x-crud.index :array="$companies" :routeCreate="route('company.create')" />

    <x-slot name="js">
        {{-- <script src="{{ asset('js/pagination.js') }}"></script> --}}
    </x-slot>
</x-app-layout>
