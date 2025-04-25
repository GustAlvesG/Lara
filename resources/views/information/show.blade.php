<x-app-layout>
    <x-slot name="header">
        <div class="row">
            <div class="col-6">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    InfoClube - {{ $info->name }}
                </h2>
            </div>
            <div class="col-6">
                @include('information.partials.editDeleteButtons',
                ['model' => $info, 
                'route' => 'information', 
                'name' => 'Informação'])
            </div>
        </div>
    </x-slot>

    <x-slot name="css">
    </x-slot>

    <div class="py-6">
        @include('information.partials.information-show')
    </div>

    <div class="px-6">
       
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
