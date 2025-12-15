<!-- Carregamento do Tailwind CSS (assumido) -->
<script src="https://cdn.tailwindcss.com"></script>

<div class="max-w-4xl mx-auto my-10 p-6 bg-white shadow-2xl rounded-2xl">
    <h1 class="text-3xl font-bold mb-6 border-b pb-3" style="color:#A00001">Novo Agendamento</h1>

    <!-- FORMULÁRIO PRINCIPAL -->
    <form class="max-h-full overflow-y-auto" id="schedule-form" method="POST" action="ROTA">
        @csrf
        
        <!-- Contêiner de Notificações -->
        <div id="messages"></div>
        <div id="loading-indicator" class="hidden mb-4 p-3 bg-blue-100 border border-blue-400 text-blue-700 rounded-lg flex items-center">
            <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span>Carregando opções...</span>
        </div>

        <!-- ----------------------------------- -->
        <!-- PASSO 1: MODALIDADE/QUADRA -->
        <!-- ----------------------------------- -->
        <div id="step-1-wrapper">
            <div id="step-1-container" class="mb-6 p-6 border-2 border-[#A00001] rounded-xl bg-[#FFE0E0] shadow-md step-active">
                <label for="quadra_id" class="flex items-center text-xl font-extrabold mb-3" style="color:#A00001">
                    <span class="mr-3 bg-[#A00001] text-white w-8 h-8 flex items-center justify-center rounded-full text-lg">1</span>
                    Selecione a Modalidade/Quadra
                </label>
                <select id="quadra_id" name="quadra_id" required
                        class="w-full py-3 px-4 border border-[#A00001] rounded-lg focus:ring-[#A00001] focus:border-[#A00001] text-lg shadow-sm">
                    <option value="">Aguardando carregamento...</option>
                </select>
                <input type="hidden" id="selected_quadra_name" name="quadra_name">
            </div>
        </div>
        
        <!-- ----------------------------------- -->
        <!-- PASSO 2: DATA (CONJ. DE BOTÕES) -->
        <!-- ----------------------------------- -->
        <div id="step-2-wrapper" 
             class="max-h-0 opacity-0 overflow-hidden transition-all duration-500 ease-in-out">
            <div id="step-2-container" 
                 class="mb-6 p-6 border border-gray-200 rounded-xl bg-white shadow-lg">
                <label class="flex items-center text-xl font-extrabold mb-3" style="color:#A00001">
                    <span class="mr-3 bg-[#A00001] text-white w-8 h-8 flex items-center justify-center rounded-full text-lg">2</span>
                    Selecione a Data
                </label>
                <!-- Container para os botões de Data -->
                <div id="available-dates-container" class="grid grid-cols-4 md:grid-cols-7 gap-3">
                    <!-- Datas serão injetadas aqui via JavaScript -->
                </div>
                <!-- Input hidden para submeter a data selecionada -->
                <input type="hidden" id="selected_date_value" name="date" required disabled> 
            </div>
        </div>

        <!-- ----------------------------------- -->
        <!-- PASSO 3: HORÁRIO (SELEÇÃO MÚLTIPLA) -->
        <!-- ----------------------------------- -->
        <div id="step-3-wrapper" 
             class="max-h-0 opacity-0 overflow-hidden transition-all duration-500 ease-in-out">
            <div id="step-3-container" 
                 class="mb-6 p-6 border border-gray-200 rounded-xl bg-white shadow-lg">
                <label class="flex items-center text-xl font-extrabold text-gray-800 mb-3">
                    <span class="mr-3 bg-gray-400 text-white w-8 h-8 flex items-center justify-center rounded-full text-lg">3</span>
                    Selecione o Horário (<span id="limit-info" class="">Máx: 1</span>)
                </label>
                <div id="slots-container" class="grid grid-cols-3 md:grid-cols-6 gap-3">
                    <!-- Horários serão injetados aqui via JavaScript -->
                </div>
                
                <input type="hidden" id="selected_slot" name="start_time_slots">
                
                <!-- Botão "Continuar" para ir para o Passo 4 -->
                <div id="continue-to-step-4" class="flex justify-center mt-6">
                    <button type="button" onclick="goToStep(4)" 
                            class="px-8 py-3 bg-[#A00001] text-white rounded-lg shadow-xl 
                                    hover:bg-[#7A0000] transition duration-200 font-extrabold text-lg">
                        Continuar (Passo 4)
                    </button>
                </div>
            </div>
        </div>

        <div id="resume-wrapper" 
            class="max-h-0 opacity-0 overflow-hidden transition-all duration-500 ease-in-out">
            <div id="resume-container" 
                 class="p-6 border border-gray-200 rounded-xl bg-white shadow-lg">
                <label class="flex items-center text-xl font-extrabold text-gray-800 mb-3">
                    <span class="mr-3 text-white w-8 h-8 flex items-center justify-center rounded-full text-lg bg-green-600"></span>
                    Resumo da Reserva
                </label>
                <!-- ID CORRIGIDO PARA resume-details -->
                <div id="resume-details" class="space-y-3">
                    <!-- Detalhes do Resumo serão injetados aqui via JavaScript -->
                </div>
                
            </div>

        </div>

        <!-- ----------------------------------- -->
        <!-- NOVO PASSO 4: SELEÇÃO DE USUÁRIO -->
        <!-- ----------------------------------- -->
        <div id="step-4-wrapper" 
             class="mb-6 max-h-0 opacity-0 overflow-hidden transition-all duration-500 ease-in-out">
            <div id="step-4-container" 
                 class="p-6 border border-gray-200 rounded-xl bg-white shadow-lg">
                <label for="member_id" class="flex items-center text-xl font-extrabold text-gray-800 mb-3">
                    <span class="mr-3 bg-gray-400 text-white w-8 h-8 flex items-center justify-center rounded-full text-lg">4</span>
                    Identificação do Sócio
                </label>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Campo de pesquisa/seleção de usuário (Exemplo básico) -->
                    <div>
                    <input name="member_search_cpf" type="text" id="member_search_cpf" placeholder="CPF do Sócio"
                        class="w-full py-3 px-4 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 text-lg shadow-sm mb-4">
                    </div>
                    <div>
                    <input name="member_search_title" type="text" id="member_search_title" placeholder="Matrícula do Sócio"
                    class="w-full py-3 px-4 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 text-lg shadow-sm mb-4">
                    </div>
                    <div>
                    <input name="member_search_birthdate" type="date" id="member_search_birthdate" placeholder="Data de Nascimento do Sócio"
                    class="w-full py-3 px-4 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 text-lg shadow-sm mb-4">
                    </div>
                </div>
                <div id="member-selection-info" class="p-3 bg-yellow-50 text-yellow-700 rounded-lg hidden">
                    <!-- Informação do sócio selecionado será injetada aqui -->
                </div>

                {{-- <input type="hidden" id="member_id" name="member_id" required> --}}
            </div>
        </div>

        <!-- ----------------------------------- -->
        <!-- BOTÃO DE SUBMISSÃO FINAL (OCULTO) -->
        <!-- ----------------------------------- -->
        <div id="submit-container" class="flex justify-center mt-8 hidden">
            <button type="button" onclick="goToSubmit()"
                    class="px-8 py-3 bg-green-600 text-white rounded-lg shadow-xl 
                            hover:bg-green-700 transition duration-200 font-extrabold text-xl">
                Confirmar e Agendar
            </button>
        </div>
    </form>
</div>


<!-- Lógica JavaScript (AJAX + Transições) -->
<script>
    let max_limit = 0;
    let selected_options = [];
    //Get API TOken by .env
    const API_TOKEN = "{{ config('services.api.token') }}";
    // URL da nova API para todas as modalidades (assumindo que você a criou)
    const API_ALL_MODALITIES = "{{ route('api.placegroup.indexByCategory', 'esportiva') }}";
    const API_AVAILABLE_DATES = "{{ route('schedule.getScheduledDates') }}"; // NOVA API NECESSÁRIA
    const API_SLOTS = "{{ route('api.schedule.getTimeOptions') }}"; // Antiga API de slots
    // const API_MEMBERS_SEARCH = ROTA; // API de busca de sócios
    const API_SUBMIT_SCHEDULE = "{{ route('api.schedule.store') }}"; // API de submissão de agendamento
    
    // Elementos de UI (Mantidos)
    const loadingIndicator = document.getElementById('loading-indicator');
    const quadraSelect = document.getElementById('quadra_id');
    const dateInputHidden = document.getElementById('selected_date_value'); 
    const availableDatesContainer = document.getElementById('available-dates-container'); 
    const slotsContainer = document.getElementById('slots-container');
    const submitContainer = document.getElementById('submit-container');
    const continueButton = document.getElementById('continue-to-step-4');

    // Wrappers de Passos
    const step1Wrapper = document.getElementById('step-1-wrapper');
    const step1Container = document.getElementById('step-1-container');
    const step2Wrapper = document.getElementById('step-2-wrapper');
    const step2Container = document.getElementById('step-2-container');
    const step3Wrapper = document.getElementById('step-3-wrapper');
    const step3Container = document.getElementById('step-3-container');
    const step4Wrapper = document.getElementById('step-4-wrapper');
    const step4Container = document.getElementById('step-4-container');
    const resumeWrapper = document.getElementById('resume-wrapper');
    // Badges de Número do Passo
    const step2Badge = document.querySelector('#step-2-container span:first-child');
    const step3Badge = document.querySelector('#step-3-container span:first-child');
    const step4Badge = document.querySelector('#step-4-container span:first-child'); 
    const limitInfoSpan = document.getElementById('limit-info');

    //INPUTS DE SÓCIO
    const member_search_cpf = document.getElementById('member_search_cpf');
    const member_search_title = document.getElementById('member_search_title');
    const member_search_birthdate = document.getElementById('member_search_birthdate');

    //On input in member_input
    member_search_cpf.addEventListener('input', showSubmitButton);
    member_search_title.addEventListener('input', showSubmitButton);
    member_search_birthdate.addEventListener('input', showSubmitButton);

    const MAX_HEIGHT_PX = '1800px'; 

    
    // Funções de Design
    function updateDesign(step, isActive) {
        let badge, container;
        console.log("Updating design for step " + step + " to " + (isActive ? "active" : "inactive"));

        if (step !== 1){
            document.querySelectorAll('.step-active').forEach(el => {
                el.classList.remove('step-active', 'border-2', 'border-[#A00001]', 'bg-[#FFE0E0]');
                el.classList.add('border-gray-200', 'bg-white');
            });

        }
            

        if (step == 1) {
            badge = document.querySelector('#step-1-container span:first-child');
            container = step1Container;
        } 
        else if (step === 2) {
            badge = step2Badge;
            container = step2Container;
        } else if (step === 3) {
            badge = step3Badge;
            container = step3Container;
        } else if (step === 4) {
            badge = step4Badge;
            container = step4Container;
        }
        else {
            return;
        }

        //Forach .step-active remove

        if (isActive) {
            container.classList.remove('border-gray-200', 'bg-white');
            container.classList.add('border-2', 'border-[#A00001]', 'bg-[#FFE0E0]', 'step-active');

            badge.classList.remove('bg-gray-400');
            badge.classList.add('bg-[#A00001]');
            if (step === 2) dateInputHidden.disabled = false; // Habilita o hidden input
        } else {
            container.classList.remove('border-2', 'border-[#A00001]', 'bg-[#FFE0E0]', 'step-active');
            container.classList.add('border-gray-200', 'bg-white');
            badge.classList.remove('bg-[#A00001]');
            badge.classList.add('bg-gray-400');
            if (step === 2) dateInputHidden.disabled = true; // Desabilita o hidden input
        }
    }

    // Função para gerenciar a transição de abertura/fechamento
    function toggleStep(wrapper, open) {
        if (open) {
            wrapper.style.maxHeight = wrapper.scrollHeight + 6 + 'px'; // Usa scrollHeight para abrir
            wrapper.classList.remove('opacity-0');
            wrapper.classList.add('opacity-100');
        } else {
            wrapper.style.maxHeight = wrapper.scrollHeight + 'px'; 
            wrapper.classList.remove('opacity-100');
            wrapper.classList.add('opacity-0');

            setTimeout(() => {
                wrapper.style.maxHeight = '0';
            }, 10); 
        }
    }

    // ----------------------------------------------------
    // PASSO 1: IMPLEMENTAÇÃO DA FUNÇÃO QUE ESTAVA FALTANDO
    // ----------------------------------------------------
    function fetchAllModalities() {
        loadingIndicator.classList.remove('hidden');
        quadraSelect.disabled = true;

        fetch(API_ALL_MODALITIES)
            .then(response => response.json())
            .then(data => {
                quadraSelect.innerHTML = '<option value="">-- Selecione a Opção --</option>';
                const modalitiesArray = Array.isArray(data) ? data : Object.values(data);
                
                if (modalitiesArray.length > 0) {
                    modalitiesArray.forEach(modality => {
                        // Cria o <optgroup> para a Modalidade
                        const optgroup = document.createElement('optgroup');
                        optgroup.label = modality.category_name || modality.name || 'Outras';

                        // Adiciona as quadras como <option>s
                        const quadras = modality.quadras || modality.places || [];
                        quadras.forEach(item => {
                            const option = document.createElement('option');
                            option.value = item.id;
                            option.textContent = item.name;
                            optgroup.appendChild(option);
                        });
                        
                        quadraSelect.appendChild(optgroup);
                    });
                    quadraSelect.disabled = false;
                } else {
                    showMessage("error", "Nenhuma quadra disponível para agendamento.");
                    quadraSelect.innerHTML = '<option value="">Nenhuma opção...</option>';
                }
            })
            .catch(error => {
                showMessage("error", "Erro ao carregar modalidades iniciais.");
                console.error('Error fetching all modalities:', error);
            })
            .finally(() => {
                loadingIndicator.classList.add('hidden');
            });
    }

    // ----------------------------------------------------
    // Passo 1 (MODALIDADE): Disparado ao mudar a Modalidade/Quadra
    // ----------------------------------------------------
    quadraSelect.addEventListener('change', function() {
        const quadraId = this.value;
        
        // Sempre fecha o Passo 3, pois a Modalidade mudou
        resetStep(3); 
        
        if (!quadraId) {
            toggleStep(step2Wrapper, false); // Fecha Passo 2
            updateDesign(2, true);
            return;
        }

        
        const selectedOption = quadraSelect.options[quadraSelect.selectedIndex];
        document.getElementById('selected_quadra_name').value = selectedOption.textContent;

        //Find optgroup label
        const optgroup = selectedOption.parentElement;
        let optgroupLabel = optgroup.label;

        //Update selected quadra name with optgroup label
        selectedOption.textContent = `${optgroupLabel} - ${selectedOption.textContent}`;
    

        fetchAvailableDates(quadraId);
        
        toggleStep(step2Wrapper, true); 
        updateDesign(2, true);
    });
    
    // ----------------------------------------------------
    // NOVA API NECESSÁRIA: Busca Datas Válidas
    // ----------------------------------------------------
    function fetchAvailableDates(quadraId) {
        loadingIndicator.classList.remove('hidden');
        availableDatesContainer.innerHTML = '<span>Carregando Datas...</span>';
        fetch(`${API_AVAILABLE_DATES}/${quadraId}`) 
            .then(response => response.json())
            .then(data => {
                availableDatesContainer.innerHTML = '';

                if (data.length > 0) {

                    data.forEach(dateStr => {

                        // Cria Botão para Data
                        const button = document.createElement('button');
                        button.type = 'button';
                        button.value = dateStr; 
                        
                        // Formato de exibição: DD/MM (ex: 05/12)
                        button.textContent = formatDateForButton(dateStr); 
                        
                        // Design do botão de data com hover elegante
                        button.className = 'date-button p-2 text-center bg-gray-100 text-gray-800 rounded-lg font-bold shadow-sm transition transform hover:scale-105 hover:bg-[#FF7F7F]';
                        
                        // Evento de click que substitui o change do select
                        button.onclick = () => selectDate(button);
                        
                        availableDatesContainer.appendChild(button);
                    });
                    
                    updateDesign(2, true);
                } else {
                    showMessage("error", "Nenhuma data disponível para esta quadra.");
                    updateDesign(2, false); 
                    toggleStep(step2Wrapper, false);
                }
            })
            .catch(error => {
                showMessage("error", "Erro ao carregar datas disponíveis.");
                console.error('Error fetching available dates:', error);
                updateDesign(2, false);
            })
            .finally(() => {
                loadingIndicator.classList.add('hidden');
                // Recalcula max-height após carregar o conteúdo
                toggleStep(step2Wrapper, true); 
            });
    }

    // ----------------------------------------------------
    // NOVO: Função para selecionar o botão de Data
    // ----------------------------------------------------
    function selectDate(button) {
        const date = button.value;
        const quadraId = quadraSelect.value;
        
        // 1. Aplicar estilo de seleção
        document.querySelectorAll('.date-button').forEach(btn => {
            btn.classList.remove('bg-[#A00001]', 'text-white');
            btn.classList.add('bg-gray-100', 'text-gray-800');
        });
        button.classList.remove('bg-gray-100', 'text-gray-800');
        button.classList.add('bg-[#A00001]', 'text-white');

        // 2. Atualizar valor escondido
        dateInputHidden.value = date;

        // 3. Prosseguir para o Passo 3 (Horários)
        resetStep(3); // Garante que o passo 3 está zerado
        fetchSlots(date, quadraId);
    }


    // Formata a string de data (YYYY-MM-DD) para DD/MM para o botão
    function formatDateForButton(dateString) {
        const parts = dateString.split('-'); // ["YYYY", "MM", "DD"]
        return `${parts[2]}/${parts[1]}`; // DD/MM
    }

    // Formata a string de data (YYYY-MM-DD) para um formato amigável (ex: Sex, 05/Dez) - Mantida
    function formatDateForDisplay(dateString) {
        const date = new Date(dateString + 'T00:00:00'); 
        const options = { weekday: 'short', day: '2-digit', month: 'short' };
        return date.toLocaleDateString('pt-BR', options).replace('.', '');
    }

    function resetStep(stepNumber) {
        if (stepNumber === 2) {
            availableDatesContainer.innerHTML = '<span>Aguardando seleção da Quadra...</span>'; // Reseta o container
            toggleStep(step2Wrapper, false);
            updateDesign(2, false); 
            dateInputHidden.disabled = true; // Desabilita o hidden input
        }
        if (stepNumber === 3) {
            toggleStep(step3Wrapper, false);
            document.getElementById('slots-container').innerHTML = '';
            document.getElementById('selected_slot').value = '';
            // document.getElementById('submit-container').classList.add('hidden');
            updateDesign(3, false);
        }
        document.getElementById('messages').innerHTML = '';
    }


    function fetchSlots(date, quadraId) { 
        loadingIndicator.classList.remove('hidden');
        slotsContainer.innerHTML = '<span>Carregando Horários...</span>';
        
        fetch(API_SLOTS, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${API_TOKEN}`,
            },
            body: JSON.stringify({ date: date, place_id: quadraId })
        })
        .then(response => response.json())
        .then(data => {
            const options = data.options;
            max_limit = data.quantity || 1; // Garante que o limite seja pelo menos 1
            selected_options = []; // Limpa seleções antigas
            limitInfoSpan.textContent = `Máx: ${max_limit}`;
            
            slotsContainer.innerHTML = '';
            continueButton.classList.add('hidden'); // Esconde o botão Continuar

            if (options.length > 0) {
                options.forEach(slot => {
                    if (slot[2] != 0) return; // Pula se não disponível
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.value = slot[0] + "," + slot[1]; // HH:MM,HH:MM
                    button.textContent = slot[0] + " - " + slot[1]; 
                    button.className = 'slot-button px-3 py-2 bg-gray-100 text-gray-800 rounded-lg font-bold shadow-sm transition transform hover:scale-105 hover:bg-[#FF7F7F]'
                    button.onclick = () => selectSlot(button);
                    slotsContainer.appendChild(button);
                });

                updateDesign(3, true);
                toggleStep(step3Wrapper, true); 
            } else {
                showMessage("error", "Nenhum horário disponível para a data selecionada.");
                updateDesign(3, false); 
                toggleStep(step3Wrapper, false);
            }
        })
        .catch(error => {
            showMessage("error", "Erro ao buscar horários disponíveis.");
            console.error(error);
        })
        .finally(() => {
            loadingIndicator.classList.add('hidden');
        });
    }

    function selectSlot(button) {
        const time = button.value; // Formato: HH:MM,HH:MM (Start, End)
        const times = time.split(",");
        const index = selected_options.findIndex(o => o[0] === times[0] && o[1] === times[1]);
        

        // 1. Lógica de Desmarcação (Duplo Clique)
        if (index !== -1) {

            selected_options.splice(index, 1);
            button.classList.remove('bg-[#A00001]', 'text-white', 'shadow-lg');
            button.classList.add('bg-[#FFE0E0]', 'text-[#A00001]', 'shadow-sm');
            showMessage("success", `Horário ${times[0]} - ${times[1]} desmarcado.`);
        } 
        // 2. Lógica de Seleção (Se houver limite e não exceder)
        else {

            if (selected_options.length >= max_limit) {

                showMessage("error", `Você atingiu o limite de ${max_limit} seleções permitidas.`);
                return;
            }

            
            selected_options.push([times[0], times[1]]);
            button.classList.remove('bg-[#FFE0E0]', 'text-[#A00001]', 'shadow-sm');
            button.classList.add('bg-[#A00001]', 'text-white', 'shadow-lg');
            
            showMessage("success", `Horário ${times[0]} - ${times[1]} selecionado.`);
        }


        
        updateSlotsUI(); // Atualiza visibilidade do botão Continuar
        
        // Sempre fecha o Passo 4 se houver mudança de horário
        resetStep(4);
    }

    function goToStep(nextStep) {
        if (nextStep === 4) {
            if (selected_options.length === 0) {
                showMessage('error', 'Selecione ao menos um horário para continuar.');
                return;
            }
            // 1. Prepara os dados (converte [start, end] para string "start,end;start,end...")
            const slotValues = selected_options.map(o => o.join(','));
            document.getElementById('selected_slot').value = slotValues.join(';');

            // 2. Transição: Fecha Todos os Passos Anteriores e Abre o Passo 4 e o Resumo
            toggleStep(step1Wrapper, false);
            updateDesign(1, false);
            toggleStep(step2Wrapper, false);
            updateDesign(3, false);
            toggleStep(step3Wrapper, false);
            updateDesign(3, false);

            toggleStep(resumeWrapper, true);

            makeResume();

            toggleStep(step4Wrapper, true);
            updateDesign(4, true);

            // // 3. Oculta o botão Continuar e revela o botão Finalizar (se sócio já estiver selecionado)
            continueButton.classList.add('hidden');


        } else if (nextStep === 5) { // Submissão Final
             if (!document.getElementById('member_id').value) {
                showMessage('error', 'Selecione um sócio antes de finalizar.');
                return;
            }
            document.getElementById('schedule-form').submit();
        }
    }

    function makeResume() {
        const detailsContainer = document.getElementById('resume-details');
        const quadraName = document.getElementById('selected_quadra_name').value;
        const selectedDateDisplay = formatDateForDisplay(document.getElementById('selected_date_value').value);
        
        let html = '';

         // 1. QUADRA E DATA (Versão mais compacta)
        html += `
            <div class="grid grid-cols-3 gap-4 mb-3 p-3 bg-gray-50 rounded-lg border border-gray-200">
                <div>
                    <span class="text-sm font-medium text-gray-600">Local:</span>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <span class="inline-block bg-[#FFE0E0] text-[#A00001] text-sm font-semibold px-3 py-1 rounded-full shadow-sm">${quadraName}</span>
                    </div>
                </div>
                <div>
                    <span class="text-sm font-medium text-gray-600">Data:</span>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <span class="inline-block bg-[#FFE0E0] text-[#A00001] text-sm font-semibold px-3 py-1 rounded-full shadow-sm">${selectedDateDisplay}</span>
                    </div>
                </div>
                <div>
                <span class="text-sm font-medium text-gray-600">Horários:</span>
                `;

        // 2. HORÁRIOS SELECIONADOS
        selected_options.sort((a, b) => a[0].localeCompare(b[0])); // Ordena por hora
        
        const slotBadges = selected_options.map(slot => 
            `<span class="inline-block bg-[#FFE0E0] text-[#A00001] text-sm font-semibold px-3 py-1 rounded-full shadow-sm">
                ${slot[0]} - ${slot[1]}
            </span>`
        ).join(' ');

        html += `<div class="mt-2 flex flex-wrap gap-2">${slotBadges}</div></div>
            </div>`;
        
        detailsContainer.innerHTML = html;
        toggleStep(resumeWrapper, true); // Recalcula a altura do resumo após a injeção
    }

    function showSubmitButton() {


        if (!member_search_cpf.value || !member_search_title.value || !member_search_birthdate.value) {
            // showMessage('error', 'Preencha todos os campos do sócio para continuar.');
            return;
        }

        submitContainer.classList.remove('hidden');
    }

    function checkCPF(cpf) {

        return true; // Placeholder
    }
    

    function goToSubmit() {

        if (!document.getElementById('member_search_cpf').value) {
            showMessage('error', 'Selecione um sócio antes de finalizar.');
            return;
        }
        let member_search_cpf = document.getElementById('member_search_cpf').value;
        let member_search_title = document.getElementById('member_search_title').value;
        let member_search_birthdate = document.getElementById('member_search_birthdate').value;

        $times = selected_options;
        $date = document.getElementById('selected_date_value').value;
        $status_id = 1; // Presumindo que 1 é o status "Confirmado"
        $place_id = document.getElementById('quadra_id').value;

        $body = [];
        for (let i = 0; i < $times.length; i++) {
            $body.push({
                cpf: member_search_cpf,
                title: member_search_title,
                birthDate: member_search_birthdate,
                start_schedule: $date + ' ' + $times[i][0],
                end_schedule: $date + ' ' + $times[i][1],
                status_id: $status_id,
                place_id: $place_id,
                price: 0
            });
        }

        fetch(API_SUBMIT_SCHEDULE, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${API_TOKEN}`
            },
            body: JSON.stringify( $body )
        })
        .then(response => response.json())
        .then(data => {

            if (data.success) {
                showMessage('success', 'Agendamento realizado com sucesso!');
                // Redireciona ou reseta o formulário conforme necessário
                window.location.href = "{{ route('schedule.index') }}";
            } else {
                showMessage('error', data.message || 'Erro ao realizar agendamento.');
            }
        })
        .catch(error => {
            console.error('Error submitting schedule:', error);
            showMessage('error', 'Erro ao realizar agendamento.');
        });
    }

    function updateSlotsUI() {
        // Mostra/Oculta o botão Continuar
        if (selected_options.length > 0) {
            continueButton.classList.remove('hidden');
            toggleStep(step3Wrapper, true); 
        } else {
            continueButton.classList.add('hidden');
        }
    }

    function showMessage(type, message) {
        
     }

    // Inicialização ao carregar a página
    document.addEventListener('DOMContentLoaded', function() {
        // Inicializa a altura correta para as transições
        step2Wrapper.style.maxHeight = '0';
        step3Wrapper.style.maxHeight = '0';
        
        // Garante que os badges iniciais estejam cinzas
        updateDesign(2, false);
        updateDesign(3, false);
        updateDesign(1, true);

        fetchAllModalities();
    });

</script>