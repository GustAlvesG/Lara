<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Grupo de Espaços') }}

        </h2>
    </x-slot>

    <x-slot name="css">
    </x-slot>

    <div class="py-6">
        <div class="mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-6 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg row">
                <div class="col-6">
                    @include('partials.search')
                </div>
                <div class="col-6 flex justify-center items-center"> <!-- Adicionado classes do Flexbox -->
                    <x-primary-button-a href="{{ route('place-group.create') }}">
                        Novo Grupo
                    </x-primary-button-a>
                </div>
            </div>
        </div>
        <br>
        <div class="mx-auto sm:px-6 lg:px-8 space-y-6 page-group">
            <div class="p-6 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg page" data-limit="5" data-actual="">
                @foreach ($groups as $item)
                    @include('location.placeGroup.partials.element')
                @endforeach
            </div>

            <div class="flex justify-center sm:px-6 lg:px-8 space-y-6 my-3">
                <div class="p-6 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg pagination">
                    @include('partials.navPagination')
                </div>
            </div>
        </div>

       
    </div>

    <x-slot name="js">
        <script src="{{ asset('js/pagination.js') }}"></script>
    </x-slot>
</x-app-layout>
