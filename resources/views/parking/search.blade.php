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
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <!-- Inclusão do componente de totais do painel -->
                        @include('parking.partials.dashTotals')
                        <br>
                    </div>
                    <!-- Inclusão do componente de formulário -->
                    @include('parking.partials.form')
                </div>
            </div>
        </div>
    </div>
<!-- Fim do layout da aplicação -->
</x-app-layout>