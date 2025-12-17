<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Sistema de Identificação de Veículos') }}
        </h2>
    </x-slot>

    <x-slot name="css">
        <link rel="stylesheet" href="{{ asset('css/result-cars.css') }}">
    </x-slot>

    <div class="py-4">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-2xl sm:rounded-xl">

                <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                    <h1 class="text-2xl font-extrabold text-gray-800 dark:text-white mb-4">Dados do Acesso</h1>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

                        <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600">
                            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Placa</div>
                            <div class="text-xl font-bold text-gray-800 dark:text-gray-100 mt-1">{{ $car['plate'] }}</div>
                        </div>

                        <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600">
                            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Cor</div>
                            <div class="text-xl font-bold text-gray-800 dark:text-gray-100 mt-1">
                                {{ isset($car['color']) ? strtoupper($car['color']) : 'Não encontrado' }}
                            </div>
                        </div>

                        <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600">
                            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Quantidade de Acessos</div>
                            <div class="text-xl font-bold text-gray-800 dark:text-gray-100 mt-1">{{ count($data) }}</div>
                        </div>

                    </div>
                </div>

                <div class="p-6">
                    <h2 class="text-xl font-extrabold text-gray-800 dark:text-white mb-4 border-b border-gray-200 dark:border-gray-700 pb-2">
                        Condutores Mais Prováveis
                    </h2>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        @foreach ($probaly as $key => $item)
                            @php
                                $text = explode(' | ', $key);
                            @endphp
                            <div class="p-4 bg-white dark:bg-gray-700 rounded-lg shadow-md border border-gray-100 dark:border-gray-600 hover:shadow-lg transition duration-150">
                                <h3 class="font-semibold text-base text-gray-900 dark:text-white leading-tight truncate" title="{{ $text[0] }}">
                                    {{ $text[0] }}
                                </h3>
                                <p class="text-sm text-gray-600 dark:text-gray-300 mt-1">
                                    {{ $text[1] }} -
                                    <span class="font-bold text-indigo-600 dark:text-indigo-400">{{ number_format($item, 2) }}%</span>
                                </p>
                            </div>
                        @endforeach
                    </div>
                </div>

                <hr class="my-4 border-gray-200 dark:border-gray-700">

                <div class="p-6">
                    <h2 class="font-extrabold text-xl text-gray-800 dark:text-white mb-4 border-b border-gray-200 dark:border-gray-700 pb-2">
                        Histórico de Acessos
                    </h2>

                    @foreach($data as $log)
                        {{-- 
                        Nota: O arquivo 'parking.partials.dataItemAccess' também 
                        precisará ser adaptado internamente para suportar o modo escuro 
                        (ex: mudar text-gray-800 para dark:text-gray-200).
                        --}}
                        @include('parking.partials.dataItemAccess', ['log' => $log])
                    @endforeach
                </div>

            </div>
        </div>
    </div>



    
</x-app-layout>
