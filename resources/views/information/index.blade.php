<x-app-layout>
    <x-slot name="header">
       <div class="flex justify-between items-center">
        <div class="div">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                InfoClube
            </h2>
        </div>
        
        
        
        <div class="div">

      <div class="flex items-center"> 
            <x-primary-button-a href="{{ route('information.create') }}">
                {{ __('Nova Informação') }}
            </x-primary-button-a>
            
        </div>
            
        </div>
    </x-slot>

    <x-slot name="css">
        <link rel="stylesheet" href="{{ asset('css/list.css') }}">
    </x-slot>


    <div class="py-6">
        {{-- <div class="mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-6 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg row">
                <div class="col-6">
                    @include('partials.search')
                </div>
                
            </div>
        </div>
        <br> --}}
        <div class="mx-auto sm:px-6 lg:px-8 space-y-6 page-group">
            <div class="p-6 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg page" data-limit="5" data-actual="">
                {{-- @foreach ($infos as $item)
                    @include('information.partials.element', ['item' => $item])
                @endforeach --}}

                <!-- NOVA ESTRUTURA COM TAILWIND GRID (Solução) -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach ($infos as $item)
                        <!-- A classe 'col-span-1' é implícita no grid -->
                        <div> 
                            @include('information.partials.element', ['item' => $item])
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="flex justify-center sm:px-6 lg:px-8 space-y-6 my-3">
                <div class="p-6 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg pagination">
                    @include('partials.navPagination')
                </div>
            </div>
        </div>
    </div>

    <x-slot name="js">
        {{-- <script src="{{ asset('js/pagination.js') }}"></script> --}}
    </x-slot>
</x-app-layout>