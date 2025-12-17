<div class="max-w-3xl mx-auto bg-white dark:bg-gray-800 shadow-2xl rounded-2xl p-8 transform hover:shadow-3xl transition duration-500">

    <div class="flex justify-between items-start mb-6 border-b border-gray-200 dark:border-gray-700 pb-4">
        <div>
            <h2 class="text-3xl font-extrabold text-gray-800 dark:text-white">Detalhes do Agendamento</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Gerenciamento de Reserva</p>
        </div>
    
        <div class="flex space-x-6">
            
            <div class="flex flex-col items-center">
                <label class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Agendamento</label>
                <span class="text-2xl font-bold bg-indigo-500 text-white px-4 py-1 rounded-full shadow-md"> 
                    {{ $item->id ?? '' }} 
                </span>
                <input type="hidden" name="place_group_id" value="{{ $item->id ?? '' }}">
            </div>

            <div class="flex flex-col items-center">
                <label class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Status</label>
                <x-status-span :status="$item->status" :text="true" />
            </div>

        </div>
    </div>

    <div>
        <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-200 mb-3 border-b border-dashed border-gray-200 dark:border-gray-600 pb-1">Período</h3>
        
        <div class="space-y-4">
            
            <div class="mb-4">
                <label for="data-agenda" class="block text-sm font-medium text-gray-600 dark:text-gray-300">📅 Data do Agendamento</label>
    
                <x-text-input type="date" name="id" id="id" 
                    class="form-control w-full dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 dark:[color-scheme:dark]" 
                    value="{{ \Carbon\Carbon::parse($item->start_schedule)->format('Y-m-d') }}"  disabled/>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="hora-inicio" class="block text-sm font-medium text-gray-600 dark:text-gray-300">Hora Início</label>
                    
                    <x-text-input type="time" name="id" id="id" 
                        class="form-control w-full dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 dark:[color-scheme:dark]" 
                        value="{{ \Carbon\Carbon::parse($item->start_schedule)->format('H:i') }}"   disabled/>
                </div>
                <div>
                    <label for="hora-fim" class="block text-sm font-medium text-gray-600 dark:text-gray-300">Hora Fim</label>

                    <x-text-input type="time" name="id" id="id" 
                        class="form-control w-full dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 dark:[color-scheme:dark]" 
                        value="{{ \Carbon\Carbon::parse($item->end_schedule)->format('H:i') }}"   disabled/>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-6">
        <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-200 mb-3 border-b border-dashed border-gray-200 dark:border-gray-600 pb-1">Localização e Pessoa</h3>
        
        <div class="space-y-4">
            <div class="mb-3">
                <label for="local" class="block text-sm font-medium text-gray-600 dark:text-gray-300">Local</label>
                
                <x-text-input type="text" name="id" id="id" 
                    class="form-control w-full dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300" 
                    value="{{ $item->place->name ?? '' }}" disabled/>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-600 dark:text-gray-300">Responsável</label>
                
                <x-text-input type="text" name="id" id="id" 
                    class="form-control w-full dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300" 
                    value="{{ $item->member->name ?? '' }}" disabled/>
            </div>
        </div>
    </div>

</div>