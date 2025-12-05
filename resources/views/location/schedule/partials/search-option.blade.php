<div class="pt-4 border-t border-gray-200">
        <form method="GET" action="{{ route('schedule.index') }}">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label for="search_modalidade" class="block text-sm font-medium text-gray-700">Modalidade</label>
                    <select id="search_modalidade" name="modalidade" 
                            class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        <option value="">Todas as Modalidades</option>
                        <option value="tennis">Tennis</option>
                        <option value="natacao">Natação</option>
                    </select>
                </div>

                <div>
                    <label for="search_quadra" class="block text-sm font-medium text-gray-700">Quadra</label>
                    <select id="search_quadra" name="quadra" 
                            class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        <option value="">Todas as Quadras</option>
                        <option value="1">Quadra A</option>
                        <option value="2">Quadra B</option>
                    </select>
                </div>

                
                
                <div>
                    <label for="search_status" class="block text-sm font-medium text-gray-700">Status</label>
                    <select id="search_status" name="status" 
                            class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        <option value="">Todos os Status</option>
                        <option value="1">Confirmado</option>
                        <option value="3">Pendente</option>
                        <option value="0">Cancelado</option>
                        <option value="10">Antigo</option>

                    </select>
                </div>
                
                <div>
                    <label for="search_data" class="block text-sm font-medium text-gray-700">Data</label>
                    <input type="date" id="search_data" name="data" 
                           class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>
                
                <div class="md:col-span-2"> <label for="search_socio" class="block text-sm font-medium text-gray-700">Sócio (Nome ou CPF)</label>
                    <input type="text" id="search_socio" name="socio" placeholder="Nome completo ou CPF do sócio"
                           class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>

            </div>
            
            <div class="flex justify-end pt-2">
               <x-primary-button type="submit">
                   Filtrar
                </x-primary-button>
            </div>
            
        </form>
    </div>