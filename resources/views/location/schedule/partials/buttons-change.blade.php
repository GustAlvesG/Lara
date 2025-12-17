<div class="space-y-4 mb-6">
    <div>
        <label for="action_status" class="block text-lg font-semibold text-gray-700 dark:text-gray-200 mb-2">Operação Desejada</label>
        
        <select id="action_status" name="action_status" 
                class="block w-full py-2.5 px-3 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 rounded-lg shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 text-base text-gray-900 dark:text-white" required>
            <option value="">Selecione a operação desejada</option>
            <option value="confirmar">Confirmar Agendamento</option>
            <option value="reagendar">Reagendar (Mudar Horário/Local)</option>
            <option value="cancelar">Cancelar Agendamento</option>
        </select>
    </div>
    
</div>

<div class="flex justify-center items-center pt-1 border-t border-gray-100 dark:border-gray-700">
    <div class="space-x-4">
        <x-primary-button type="submit">
            Salvar Alterações
        </x-primary-button>
    </div>
</div>