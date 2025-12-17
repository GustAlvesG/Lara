<h2 class="text-2xl font-extrabold text-gray-800 dark:text-white mb-6 border-b pb-3">Totais de Acesso Hoje</h2>
                    
<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
    
    <!-- Card: Total de Veículos Hoje -->
    <div class="p-6 bg-indigo-50 dark:bg-indigo-900 rounded-xl shadow-lg border-l-4 border-indigo-500  dark:border-indigo-300">
        <div class="text-4xl font-extrabold text-indigo-700 dark:text-white">
            <!-- VALOR DINÂMICO -->
            {{ $todayParkingCount }}
        </div>
        <div class="text-sm text-gray-600 mt-1 dark:text-white">
            Total de Veículos hoje
        </div>
    </div>

    <!-- Card: Total de Veículos Não Identificados -->
    <div class="p-6 bg-yellow-50 dark:bg-yellow-900 rounded-xl shadow-lg border-l-4 border-yellow-500 dark:border-yellow-300">
        <div class="text-4xl font-extrabold text-yellow-700 dark:text-white">
            <!-- VALOR DINÂMICO -->
            {{ $todayParkingNoPlate }}
        </div>
        <div class="text-sm text-gray-600 mt-1 dark:text-white">
            Total de veículos não identificados hoje
        </div>
    </div>
</div>