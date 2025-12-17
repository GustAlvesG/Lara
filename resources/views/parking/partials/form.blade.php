<h2 class="text-2xl font-extrabold text-gray-800 dark:text-white mb-4 border-b border-gray-200 dark:border-gray-700 pb-3">
    Buscar Veículo
</h2>

<form action="{{ route('parking.show') }}" method="POST">
    @csrf 
    
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
        
        <div class="md:col-span-2">
            <label for="plate" class="block font-medium text-sm text-gray-700 dark:text-white mb-1">
                Placa do veículo
            </label>
            <input id="plate" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500 bg-white dark:bg-gray-900 text-gray-900 dark:text-white" 
                label="Placa" name="plate" required>
        </div>
        
        <div class="md:col-span-1">
            <label for="datetime" class="block font-medium text-sm text-gray-700 dark:text-white mb-1">
                Data
            </label>
            <input type="date" id="datetime" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500 bg-white dark:bg-gray-900 text-gray-900 dark:text-white dark:[color-scheme:dark]" 
                label="Data e Hora" name="datetime">
        </div>
        
        <div class="md:col-span-1">
            <button type="submit" 
                    class="w-full px-6 py-2.5 bg-indigo-600 text-white rounded-lg font-bold text-base shadow-lg hover:bg-indigo-700 transition duration-150">
                Buscar
            </button>
        </div>
        
    </div>
</form>