<!-- Carregamento do Tailwind CSS (assumido) -->
<script src="https://cdn.tailwindcss.com"></script>

<div class="max-w-4xl mx-auto my-10 p-6 bg-white shadow-2xl rounded-2xl">
    <h1 class="text-3xl font-bold text-indigo-700 mb-6 border-b pb-3">Novo Agendamento</h1>

    <!-- FORMULÁRIO PRINCIPAL -->
    <form class="max-h-full overflow-y-auto" id="schedule-form" method="POST" action="ROTA">
        @csrf
        
        <!-- Contêiner de Notificações -->
        <div id="messages" class="mb-4"></div>
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
            <div id="step-1-container" class="mb-6 p-6 border-2 border-indigo-300 rounded-xl bg-indigo-50 shadow-md">
                <label for="quadra_id" class="flex items-center text-xl font-extrabold text-indigo-800 mb-3">
                    <span class="mr-3 bg-indigo-600 text-white w-8 h-8 flex items-center justify-center rounded-full text-lg">1</span>
                    Selecione a Quadra/Modalidade
                </label>
                <select id="quadra_id" name="quadra_id" required
                        class="w-full py-3 px-4 border border-indigo-400 rounded-lg focus:ring-indigo-600 focus:border-indigo-600 text-lg shadow-sm">
                    <option value="">Aguardando carregamento...</option>
                </select>
                <input type="hidden" id="selected_quadra_name" name="quadra_name">
            </div>
        </div>
        
        <!-- ----------------------------------- -->
        <!-- PASSO 2: DATA (CONJ. DE BOTÕES) -->
        <!-- ----------------------------------- -->
        <div id="step-2-wrapper" 
             class="mb-6 max-h-0 opacity-0 overflow-hidden transition-all duration-500 ease-in-out">
            <div id="step-2-container" 
                 class="p-6 border border-gray-200 rounded-xl bg-white shadow-lg">
                <label class="flex items-center text-xl font-extrabold text-gray-800 mb-3">
                    <span class="mr-3 bg-gray-400 text-white w-8 h-8 flex items-center justify-center rounded-full text-lg">2</span>
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
                 class="p-6 border border-gray-200 rounded-xl bg-white shadow-lg">
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
                            class="px-8 py-3 bg-indigo-600 text-white rounded-lg shadow-xl 
                                    hover:bg-indigo-700 transition duration-200 font-extrabold text-lg">
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
                    <span class="mr-3 bg-gray-400 text-white w-8 h-8 flex items-center justify-center rounded-full text-lg"></span>
                    Resumo da Reserva
                </label>
                <div id="resume-container" class="">
                    <!-- Horários serão injetados aqui via JavaScript -->
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
                <!-- Campo de pesquisa/seleção de usuário (Exemplo básico) -->
                <input name="member_search" type="text" id="member_search" placeholder="Buscar Sócio por CPF..."
                    class="w-full py-3 px-4 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 text-lg shadow-sm mb-4">
                
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
    const API_TOKEN = "{{ env('API_TOKEN') }}";
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
    const step2Wrapper = document.getElementById('step-2-wrapper');
    const step3Wrapper = document.getElementById('step-3-wrapper');
    const step4Wrapper = document.getElementById('step-4-wrapper');
    const resumeWrapper = document.getElementById('resume-wrapper');
    // Badges de Número do Passo
    const step2Badge = document.querySelector('#step-2-container span:first-child');
    const step3Badge = document.querySelector('#step-3-container span:first-child');
    const step4Badge = document.querySelector('#step-4-container span:first-child'); 
    const limitInfoSpan = document.getElementById('limit-info');

    //Input
    const memberSearchInput = document.getElementById('member_search');
    memberSearchInput.addEventListener('input', function() {
        const query = this.value.trim();

        if (query.length !== 11) {
            submitContainer.classList.add('hidden');
            return;
        }else {
            console.log('CPF válido inserido:', query);
            submitContainer.classList.remove('hidden');
        }
        

        // if (query.length < 3) {
        //     memberSelectionInfo.classList.add('hidden');
        //     memberIdInput.value = '';
        //     return;
        // }

        // fetch(`/api/members/search?cpf=${query}`, {
        //     headers: {
        //         'Authorization': `Bearer ${API_TOKEN}`
        //     }
        // })
        // .then(response => response.json())
        // .then(data => {
        //     if (data && data.id) {
        //         memberSelectionInfo.innerHTML = `Sócio Selecionado: <strong>${data.name}</strong> (CPF: ${data.cpf})`;
        //         memberSelectionInfo.classList.remove('hidden');
        //         memberIdInput.value = data.id;
        //     } else {
        //         memberSelectionInfo.innerHTML = 'Nenhum sócio encontrado com este CPF.';
        //         memberSelectionInfo.classList.remove('hidden');
        //         memberIdInput.value = '';
        //     }
        // })
        // .catch(error => {
        //     console.error('Erro ao buscar sócio:', error);
        //     memberSelectionInfo.innerHTML = 'Erro ao buscar sócio. Tente novamente.';
        //     memberSelectionInfo.classList.remove('hidden');
        //     memberIdInput.value = '';
        // });
    });

    const MAX_HEIGHT_PX = '1800px'; 

    
    // Funções de Design
    function updateDesign(step, isActive) {
        let badge, container;
        
        if (step === 2) {
            badge = step2Badge;
            container = step2Wrapper;
        } else if (step === 3) {
            badge = step3Badge;
            container = step3Wrapper;
        } else if (step === 4) {
            badge = step4Badge;
            container = step4Wrapper;
        }
        else {
            return;
        }
        
        if (isActive) {
            container.classList.remove('border-gray-200', 'bg-white');
            container.classList.add('border-indigo-300', 'bg-indigo-50');
            badge.classList.remove('bg-gray-400');
            badge.classList.add('bg-indigo-600');
            if (step === 2) dateInputHidden.disabled = false; // Habilita o hidden input
        } else {
            container.classList.remove('border-indigo-300', 'bg-indigo-50');
            container.classList.add('border-gray-200', 'bg-white');
            badge.classList.remove('bg-indigo-600');
            badge.classList.add('bg-gray-400');
            if (step === 2) dateInputHidden.disabled = true; // Desabilita o hidden input
        }
    }

    // Função para gerenciar a transição de abertura/fechamento
    function toggleStep(wrapper, open) {
        if (open) {
            wrapper.style.maxHeight = wrapper.scrollHeight + 'px'; // Usa scrollHeight para abrir
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
            updateDesign(2, false);
            return;
        }

        
        const selectedOption = quadraSelect.options[quadraSelect.selectedIndex];
        document.getElementById('selected_quadra_name').value = selectedOption.textContent;

        //Find optgroup label
        const optgroup = selectedOption.parentElement;
        let optgroupLabel = optgroup.label;

        console.log(optgroup);
        console.log(optgroupLabel);
        console.log(selectedOption.textContent);
        console.log(`${optgroupLabel} - ${selectedOption.textContent}`);
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
        console.log(`${API_AVAILABLE_DATES}/${quadraId}`);
        fetch(`${API_AVAILABLE_DATES}/${quadraId}`) 
            .then(response => response.json())
            .then(data => {
                availableDatesContainer.innerHTML = '';
                console.log(data);
                if (data.length > 0) {
                    console.log('Datas disponíveis encontradas:', data.length);
                    data.forEach(dateStr => {
                        console.log('Data disponível:', dateStr);
                        // Cria Botão para Data
                        const button = document.createElement('button');
                        button.type = 'button';
                        button.value = dateStr; 
                        
                        // Formato de exibição: DD/MM (ex: 05/12)
                        button.textContent = formatDateForButton(dateStr); 
                        
                        // Design do botão de data com hover elegante
                        button.className = 'date-button p-2 text-center bg-gray-100 text-gray-800 rounded-lg font-bold shadow-sm transition transform hover:scale-105 hover:bg-indigo-300';
                        
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
            btn.classList.remove('bg-indigo-600', 'text-white');
            btn.classList.add('bg-gray-100', 'text-gray-800');
        });
        button.classList.remove('bg-gray-100', 'text-gray-800');
        button.classList.add('bg-indigo-600', 'text-white');

        // 2. Atualizar valor escondido
        dateInputHidden.value = date;

        // 3. Prosseguir para o Passo 3 (Horários)
        resetStep(3); // Garante que o passo 3 está zerado
        fetchSlots(date, quadraId);
    }


    // ----------------------------------------------------
    // Funções de Utilidade e Inicialização (Reajustadas)
    // ----------------------------------------------------
    
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
            document.getElementById('submit-container').classList.add('hidden');
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
                'Authorization': `Bearer ${API_TOKEN}`
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
                    button.className = 'slot-button px-3 py-2 bg-indigo-100 text-indigo-700 rounded-lg font-bold hover:bg-indigo-300 transition shadow-sm';
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
        
        console.log('Selected Options Before:', selected_options);
        // 1. Lógica de Desmarcação (Duplo Clique)
        if (index !== -1) {
            console.log('Desmarcando horário:', times);
            selected_options.splice(index, 1);
            button.classList.remove('bg-indigo-600', 'text-white', 'shadow-lg');
            button.classList.add('bg-indigo-100', 'text-indigo-700', 'shadow-sm');
            showMessage("success", `Horário ${times[0]} - ${times[1]} desmarcado.`);
        } 
        // 2. Lógica de Seleção (Se houver limite e não exceder)
        else {

            if (selected_options.length >= max_limit) {
                console.log('Limite atingido:', max_limit);
                showMessage("error", `Você atingiu o limite de ${max_limit} seleções permitidas.`);
                return;
            }
            console.log('Adicionando horário:', times);
            
            selected_options.push([times[0], times[1]]);
            button.classList.remove('bg-indigo-100', 'text-indigo-700', 'shadow-sm');
            button.classList.add('bg-indigo-600', 'text-white', 'shadow-lg');
            
            showMessage("success", `Horário ${times[0]} - ${times[1]} selecionado.`);
        }

        console.log('Selected Options After:', selected_options);
        
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
            // toggleStep(step1Wrapper, false);
            // updateDesign(1, false);
            // toggleStep(step2Wrapper, false);
            // updateDesign(3, false);
            // toggleStep(step3Wrapper, false);
            // updateDesign(3, false);

            // toggleStep(resumeWrapper, true);

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

    function goToSubmit() {

        console.log('Preparing to submit schedule...');
        if (!document.getElementById('member_search').value) {
            showMessage('error', 'Selecione um sócio antes de finalizar.');
            return;
        }
        $member = document.getElementById('member_search').value;
        $times = selected_options;
        $date = document.getElementById('selected_date_value').value;
        $status_id = 1; // Presumindo que 1 é o status "Confirmado"
        $place_id = document.getElementById('quadra_id').value;
        console.log('Submitting schedule for member:', $member);

        $body = [];
        for (let i = 0; i < $times.length; i++) {
            $body.push({
                cpf: $member,
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
            console.log('Schedule submission response:', data);
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
            // Garante que o botão Finalizar esteja escondido até o Passo 4
            submitContainer.classList.add('hidden'); 
        } else {
            continueButton.classList.add('hidden');
        }
    }

    function showMessage(type, message) { /* ... lógica permanece igual ... */ }

    // Inicialização ao carregar a página
    document.addEventListener('DOMContentLoaded', function() {
        // Inicializa a altura correta para as transições
        step2Wrapper.style.maxHeight = '0';
        step3Wrapper.style.maxHeight = '0';
        
        // Garante que os badges iniciais estejam cinzas
        updateDesign(2, false);
        updateDesign(3, false);

        fetchAllModalities();
    });

</script>