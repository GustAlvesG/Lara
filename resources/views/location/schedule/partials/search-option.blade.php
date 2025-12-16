<div class="pt-4 border-t border-gray-200">
        <form method="POST" action="{{ route('schedule.index.filter') }}">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label for="search_modalidade" class="block text-sm font-medium text-gray-700">Modalidade</label>
                    <select id="search_modalidade" name="place_group_id" 
                            class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        <option value="">Todas as Modalidades</option>
                        <option value="tennis">Tennis</option>
                        <option value="natacao">Natação</option>
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
                    <input type="date" id="search_data" name="start_schedule" 
                           class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>
                
                <div class=""> <label for="search_socio" class="block text-sm font-medium text-gray-700">Sócio (CPF)</label>
                    <input type="text" id="search_socio" name="member_cpf" placeholder="CPF do sócio"
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


<script>
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