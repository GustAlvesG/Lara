<x-app-layout>
    <x-slot name="header">
       
        


    </x-slot>

    <x-slot name="css">
        <link rel="stylesheet" href="{{ asset('css/list.css') }}">
    </x-slot>


    <div class="py-6">
       
        <div class="mx-auto sm:px-6 lg:px-8 space-y-6 page-group">
            <div class="p-6 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg page" data-limit="5" data-actual="">
                {{-- @foreach ($infos as $item)
                    @include('information.partials.element', ['item' => $item])
                @endforeach --}}

                <!-- NOVA ESTRUTURA COM TAILWIND GRID (Solução) -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="elements-container">
                    @foreach ($infos as $item)
                        <!-- A classe 'col-span-1' é implícita no grid -->
                        <div> 
                            @include('information.partials.element', ['item' => $item])
                        </div>
                    @endforeach
                </div>

                
            </div>
            {{-- <div class="flex justify-center sm:px-6 lg:px-8 space-y-6 my-3">
                <div class="p-6 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg pagination">
                    @include('partials.navPagination')
                </div>
            </div> --}}
        </div>
    </div>

    <x-slot name="js">
        {{-- <script src="{{ asset('js/pagination.js') }}"></script> --}}
        <script src="{{ asset('js/information/filter.js') }}"></script>
    </x-slot>
</x-app-layout>