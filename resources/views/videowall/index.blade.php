<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Video Wall - Painel de Controle') }}
        </h2>
    </x-slot>

    <x-slot name="css">
        
    </x-slot>


    <div class="py-6">
        <div class="mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-6 sm:p-8 bg-white text-gray-800 dark:text-gray-200 dark:bg-gray-800 shadow sm:rounded-lg row">
                @include('videowall.partials.videoWallData', [
                    'title' => 'Video Wall 1 - Painel da Esquerda',
                    'api' => 'http://192.168.100.111',
                    'border' => 'border-r-2 border-gray-300 dark:border-gray-700'
                ])
        
            
                @include('videowall.partials.videoWallData', [
                    'title' => 'Video Wall 2 - Painel da Direita',
                    'api' => 'http://192.168.100.110'
                ])
            </div>
        </div>
    </div>

    <x-slot name="js">
        <script src="{{ asset('js/videowall/api-requests.js') }}"></script>
    </x-slot>
</x-app-layout>
