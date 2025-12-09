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
                
                <!-- Card Principal de Resultados -->
                <div class="bg-white overflow-hidden shadow-2xl sm:rounded-xl">
                    
                    <!-- ----------------------------------- -->
                    <!-- DADOS PRINCIPAIS DO ACESSO (CARDS) -->
                    <!-- ----------------------------------- -->
                    <div class="p-6 border-b border-gray-200">
                        <h1 class="text-2xl font-extrabold text-gray-800 mb-4">Dados do Acesso</h1>
                        
                        <!-- Dados do Carro em Grid -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            
                            @php
                                // Mock de dados para simular a injeção do Laravel
                                $carData = [
                                    'Placa' => 'RFC7C44',
                                    'Cor do carro' => 'PRETO',
                                    'Quantidade de acessos' => 3
                                ];
                            @endphp

                            @foreach($carData as $label => $value)
                                <div class="p-4 bg-gray-50 rounded-lg border border-gray-200">
                                    <div class="text-sm font-medium text-gray-500">{{ $label }}</div>
                                    <div class="text-xl font-bold text-gray-800 mt-1">{{ $value }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <!-- ----------------------------------- -->
                    <!-- CONDUTORES MAIS PROVÁVEIS -->
                    <!-- ----------------------------------- -->
                    <div class="p-6">
                        <h2 class="text-xl font-extrabold text-gray-800 mb-4 border-b pb-2">
                            Condutores Mais Prováveis
                        </h2>
                        

                        <!-- Condutores em Grid Responsivo -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                             @foreach ($probaly as $key => $item)
                                    @php
                                       $text = explode(' | ', $key);
                                    @endphp
                                <div class="p-4 bg-white rounded-lg shadow-md border border-gray-100 hover:shadow-lg transition duration-150">
                                    <h3 class="font-semibold text-base text-gray-900 leading-tight truncate" title="{{ $text[0] }}">
                                        {{ $text[0] }}
                                    </h3>
                                    <p class="text-sm text-gray-600 mt-1">
                                        {{ $text[1] }} - 
                                        <span class="font-bold text-indigo-600">{{ number_format($item, 2) }}%</span>
                                    </p>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <!-- ----------------------------------- -->
                    <!-- HISTÓRICO DE ACESSOS (Tabela Limpa) -->
                    <!-- ----------------------------------- -->
                    <hr class="my-4 border-gray-200">
                    
                    <div class="p-6">
                        <h2 class="font-extrabold text-xl text-gray-800 mb-4 border-b pb-2">
                            Histórico de Acessos
                        </h2>
                    
                        @foreach($data as $log)
                            @include('parking.partials.dataItemAccess', ['log' => $log])
                        @endforeach
                    </div>
                    
                </div>
            </div>
        </div>



    
</x-app-layout>
