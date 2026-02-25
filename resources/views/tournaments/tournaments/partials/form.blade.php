<div class="space-y-4">
    <div>
        <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Título do Torneio</label>
        <input type="text" name="title" id="title" value="{{ old('title') }}" required 
                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-red-800 focus:ring-red-800 transition-colors">
    </div>

    <div>
        <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Descrição</label>
        <textarea name="description" id="description" rows="3" 
                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-red-800 focus:ring-red-800 transition-colors">{{ old('description') }}</textarea>
    </div>
</div>

<hr class="border-gray-200 dark:border-gray-700">

<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    
    <div class="space-y-4">
        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Período do Torneio</h3>
        <div>
            <label for="start_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Data de Início</label>
            <input type="datetime-local" name="start_date" id="start_date" value="{{ old('start_date') }}" required 
                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-red-800 focus:ring-red-800 transition-colors">
        </div>
        <div>
            <label for="end_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Data de Término</label>
            <input type="datetime-local" name="end_date" id="end_date" value="{{ old('end_date') }}" required 
                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-red-800 focus:ring-red-800 transition-colors">
        </div>
    </div>

    <div class="space-y-4">
        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Período de Inscrições</h3>
        <div>
            <label for="start_date_subscription" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Início das Inscrições</label>
            <input type="datetime-local" name="start_date_subscription" id="start_date_subscription" value="{{ old('start_date_subscription') }}" required 
                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-red-800 focus:ring-red-800 transition-colors">
        </div>
        <div>
            <label for="end_date_subscription" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Fim das Inscrições</label>
            <input type="datetime-local" name="end_date_subscription" id="end_date_subscription" value="{{ old('end_date_subscription') }}" required 
                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-red-800 focus:ring-red-800 transition-colors">
        </div>
    </div>
</div>

<hr class="border-gray-200 dark:border-gray-700">

<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div>
        <label for="max_teams" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Máximo de Equipes (Opcional)</label>
        <input type="number" name="max_teams" id="max_teams" value="{{ old('max_teams') }}" min="2" 
                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-red-800 focus:ring-red-800 transition-colors">
    </div>
    
    <div>
        <label for="group_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">ID do Local/Grupo (Place Group)</label>
        <input type="number" name="group_id" id="group_id" value="{{ old('group_id') }}" required 
                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-red-800 focus:ring-red-800 transition-colors">
    </div>
</div>