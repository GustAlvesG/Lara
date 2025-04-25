<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            InfoClube
        </h2>
    </x-slot>

    <x-slot name="css">
        <link rel="stylesheet" href="{{ asset('css/information/list.css') }}">
        <link rel="stylesheet" href="{{ asset('css/switch.css') }}">
    </x-slot>

    <div class="py-6">        
        <div class="mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-6 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg row">
                <div class="col-6">
                    @include('information.partials.form-edit', ['route' => route('information.store')])
                </div>
                <div class="col-6 justify-center">
                    <div class="row">
                        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                            {{ __('Campos Opcionais') }}
                        </h2>
                    </div>
                    <div class="row">
                        @include('information.partials.optionalFields-edit') 
                    </div>
                    <div class="row">
                        @include('information.partials.optionalFieldsButtons-edit') 
                    </div>
                </div>
            </div>
        </div>
    </div>

    <x-slot name="js">
        <script src="{{ asset('js/information/optionalFields.js') }}"></script>
        <script src="https://cdn.ckeditor.com/4.22.1/standard/ckeditor.js"></script>
        <script>
            CKEDITOR.config.versionCheck = false;
            CKEDITOR.config.height = 400;
            CKEDITOR.replace( 'description' );
        </script>
    </x-slot>

</x-app-layout>
