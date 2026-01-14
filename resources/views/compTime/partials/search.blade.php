<div class="pt-4 mt-4 border-t border-gray-200 dark:border-gray-700">
    <form method="POST" action="{{ route('comp-time.index.filter') }}">
        @csrf
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            
            <!-- Estrutura -->
            <div>
                <label for="search_structure" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Setor</label>
                <select id="search_structure" name="structure" 
                        class="mt-1 block w-full py-2 px-3 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                    <option value="">-- Selecione a Opção --</option>
                    @foreach($structures as $structure)
                        <option value="{{ $structure }}">{{ $structure }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Nome do Funcionário -->
            <div>
                <label for="search_employee_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nome do Funcionário</label>
                <input type="text" name="employee_name" id="search_employee_name" 
                       class="mt-1 block w-full py-2 px-3 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                       placeholder="Digite o nome do funcionário" 
                       value="{{ $filters['employee_name'] ?? '' }}">
            </div>

            <!-- Código do Funcionário -->
            <div>
                <div class="flex space-x-2">
                    <div class="flex-1">
                        <label for="search_employee_code" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Matrícula</label>
                        <input type="text" name="employee_code" id="search_employee_code" 
                        class="mt-1 block w-full py-2 px-3 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                        placeholder="Digite a matrícula do funcionário" 
                        value="{{ $filters['employee_code'] ?? '' }}">
                    </div>
                    <div class="flex-1">
                        <label for="search_status" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                        <select id="search_status" name="status" 
                                class="mt-1 block w-full py-2 px-3 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            <option value="">-- Selecione a Opção --</option>
                            <option value="with_balance">Pendente de Compensação</option>
                            <option value="without_balance">Compensado</option>
                            <option value="credit_only">Apenas Horas Extras</option>
                            <option value="debit_only">Apenas Horas Faltantes</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Periodo Inicio e Fim -->
            <div>
                <label for="search_period" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Período</label>
                <div class="flex space-x-2">
                    <input type="date" name="period_start" id="search_period_start" 
                           class="mt-1 block w-full py-2 px-3 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                           value="{{ $filters['period_start'] ?? '' }}">
                    <input type="date" name="period_end" id="search_period_end" 
                           class="mt-1 block w-full py-2 px-3 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                           value="{{ $filters['period_end'] ?? '' }}">
                </div>

            </div>




        </div>
        
        <div class="flex justify-end pt-2">
            {{-- O x-primary-button geralmente se adapta sozinho se configurado, ou usa classes padrão --}}
           <x-primary-button type="submit">
                Filtrar
            </x-primary-button>
        </div>
        
    </form>
</div>


<script>
    // O JavaScript permanece o mesmo, pois ele apenas manipula os dados das opções.
    // O estilo é controlado pelas classes do elemento <select> acima.
    const API_ALL_MODALITIES = "{{ route('api.placegroup.indexByCategory', 'esportiva') }}";
    const modalidadeSelect = document.getElementById('search_modalidade');

    function fetchAllModalities() {
        // loadingIndicator.classList.remove('hidden');
        modalidadeSelect.disabled = true;

        fetch(API_ALL_MODALITIES)
            .then(response => response.json())
            .then(data => {
                modalidadeSelect.innerHTML = '<option value="">-- Selecione a Opção --</option>';
                const modalitiesArray = Array.isArray(data) ? data : Object.values(data);
                
                if (modalitiesArray.length > 0) {
                    modalitiesArray.forEach(modality => {
                        // Cria o <optgroup> para a Modalidade

                        // Adiciona as quadras como <option>s
                        const option = document.createElement('option');
                        option.value = modality.id;
                        option.textContent = modality.name;
                        
                        modalidadeSelect.appendChild(option);
                    });
                    modalidadeSelect.disabled = false;
                } else {
                    showMessage("error", "Nenhuma quadra disponível para agendamento.");
                    modalidadeSelect.innerHTML = '<option value="">Nenhuma opção...</option>';
                }
            })
            .catch(error => {
                showMessage("error", "Erro ao carregar modalidades iniciais.");
                console.error('Error fetching all modalities:', error);
            })
            .finally(() => {
                // loadingIndicator.classList.add('hidden');
            });
    }

    fetchAllModalities();
</script>