<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Grupos de Espaços
        </h2>
    </x-slot>

    <x-slot name="css">
        <link rel="stylesheet" href="{{ asset('css/information/list.css') }}">
        <link rel="stylesheet" href="{{ asset('css/switch.css') }}">
    </x-slot>
    <div class="py-6">        
        <div class="mx-auto sm:px-6 lg:px-8 space-y-6 row items-center justify-content">
            <div class="col-10">
                <div class="p-6 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg row">
                    <x-crud.create>
                        <x-slot name="route">
                            {{ route('place-group.store') }}
                        </x-slot>
                        
                        
                        <x-slot name="formInputs">
                            @include('location.placeGroup.partials.form')
                        </x-slot>  
                    </x-crud.create>
                </div>
            </div>
           
        </div>
    </div>

   
    <x-slot name="js">
        <script src="{{ asset('js/company/form-rules.js') }}"></script>
    </x-slot>

    
</x-app-layout>
