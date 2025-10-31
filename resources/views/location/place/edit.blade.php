<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Edição de Espaços - {{ $place->group->name }} / {{ $place->name }}
        </h2>
    </x-slot>

    <x-slot name="css">
        <link rel="stylesheet" href="{{ asset('css/information/list.css') }}">
        <link rel="stylesheet" href="{{ asset('css/switch.css') }}">
    </x-slot>
    <div class="py-6">        
        <div class="mx-auto sm:px-6 lg:px-8 space-y-6 items-center justify-content">
            <div class="row">
                <div class="col-10">
                    <div class="p-6 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg row">
                        <x-crud.create>
                            <x-slot name="route">
                                {{ route('place-group.updatePlace', $place->id) }}
                            </x-slot>

                            <x-slot name="put">
                                @method('PUT')
                            </x-slot>

                            <x-slot name="formInputs">
                                @include('location.place.partials.form', ['item' => $place, 'place_group' => $place->group])
                                <hr class="my-3">
                                @include('location.place.partials.form-rules')
                            </x-slot>  

                            <x-slot name="buttonText">
                                {{ __('Atualizar') }}
                            </x-slot>
                        </x-crud.create>
                    </div>
                </div>
            </div>
          
        </div>
    </div>

   
    <x-slot name="js">
        <script src="{{ asset('js/image-preview/index.js') }}"></script>
        <script src="{{ asset('js/pagination.js') }}"></script>
    </x-slot>

    
</x-app-layout>
