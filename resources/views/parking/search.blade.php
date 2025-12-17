<!-- Início do layout da aplicação -->
<x-app-layout>

    <!-- Slot para o cabeçalho da página -->
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            <!-- Título da página -->
            {{ __('Sistema de Identificação de Veículos') }}
        </h2>
    </x-slot>

    <!-- Slot para CSS adicional -->
    <x-slot name="css">
        <!-- Link para o arquivo CSS do contador de cartões -->
        <link rel="stylesheet" href="{{ asset('css/card-counter.css') }}">
    </x-slot>

    <!-- Início do conteúdo principal da página -->
    <div class="">
        <div class="py-4">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Card Principal para Contadores e Pesquisa -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-2xl sm:rounded-xl p-6">
                    
                    <!-- ----------------------------------- -->
                    <!-- SEÇÃO DE CONTADORES (CARDS) -->
                    <!-- ----------------------------------- -->
                    @include('parking.partials.dashTotals')

                    <!-- ----------------------------------- -->
                    <!-- SEÇÃO DE FORMULÁRIO DE BUSCA -->
                    <!-- ----------------------------------- -->
                    @include('parking.partials.form')
                </div>
            </div>
        </div>
    </div>
<!-- Fim do layout da aplicação -->
</x-app-layout>