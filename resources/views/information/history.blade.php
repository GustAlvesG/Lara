<x-app-layout>
    <x-slot name="header">
        <div class="row">
            <div class="col-6">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    InfoClube - Histórico de {{ $info[0]->name }}
                </h2>
            </div>
            <div class="col-6">
                
            </div>
        </div>
    </x-slot>

    <x-slot name="css">
    </x-slot>

    <div class="py-6">
        @foreach ($info as $index => $item)
            @php
                $previous = null; // Default to null if there's no previous item
                if ($index > 0) { // Check if there is a previous item
                    $previous = $info->slice($index - 1, 1)->first();
                }
            @endphp
        
            @include('information.partials.information-show', ['info' => $item, 'previous' => $previous])
        @endforeach
    </div>

    <div class="py-1">
        <div class="flex justify-center sm:px-6 lg:px-8 space-y-6 my-3">
            <div class="p-6 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg pagination">
                @include('partials.navPagination')
            </div>
        </div>
    </div>

    <x-slot name="js">
        <script src="{{ asset('js/information/optionalFields.js') }}"></script>
        <script src="{{ asset('js/information/paginationHistory.js') }}"></script>
        <script src="https://cdn.ckeditor.com/4.22.1/standard/ckeditor.js"></script>
        <script>
            CKEDITOR.config.versionCheck = false;
            CKEDITOR.config.autoGrow_onStartup = true;
            
            $(".description").each(function(index, element){
                CKEDITOR.replace( element );
            });

        </script>
    </x-slot>

</x-app-layout>
