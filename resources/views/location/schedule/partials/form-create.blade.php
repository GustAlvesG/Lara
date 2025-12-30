<script src="https://cdn.tailwindcss.com"></script>
<script>
    tailwind.config = {
        darkMode: 'media', // ou 'class' se você usar toggle manual
        theme: {
            extend: {
                colors: {
                    brand: {
                        red: '#A00001',
                        light: '#FFE0E0',
                    }
                }
            }
        }
    }
</script>

<div class="max-w-4xl mx-auto my-10 p-6 bg-white dark:bg-gray-800 shadow-2xl rounded-2xl">
    <h1 class="text-3xl font-bold mb-6 border-b border-gray-200 dark:border-gray-700 pb-3 text-[#A00001] dark:text-white">
        Novo Agendamento
    </h1>

    <form class="max-h-full overflow-y-auto" id="schedule-form" method="POST" action="ROTA">
        @csrf
        
        <div id="messages"></div>
        
        <div id="loading-indicator" class="hidden mb-4 p-3 bg-blue-100 dark:bg-blue-900 border border-blue-400 dark:border-blue-700 text-blue-700 dark:text-blue-200 rounded-lg flex items-center">
            <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-blue-500 dark:text-blue-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span>Carregando opções...</span>
        </div>

        <div id="step-1-wrapper">
            <div id="step-1-container" class="mb-6 p-6 border-2 border-[#A00001] dark:border-white rounded-xl bg-[#FFE0E0] dark:bg-transparent shadow-md step-active">
                <label for="quadra_id" class="flex items-center text-xl font-extrabold mb-3 text-[#A00001] dark:text-red-400">
                    <span class="mr-3 bg-[#A00001] dark:bg-red-600 text-white w-8 h-8 flex items-center justify-center rounded-full text-lg">1</span>
                    Selecione a Modalidade/Quadra
                </label>
                <select id="quadra_id" name="quadra_id" required
                        class="w-full py-3 px-4 border border-[#A00001] dark:border-white rounded-lg focus:ring-[#A00001] focus:border-[#A00001] text-lg shadow-sm bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
                    <option value="">Aguardando carregamento...</option>
                </select>
                <input type="hidden" id="selected_quadra_name" name="quadra_name">
            </div>
        </div>
        
        <div id="step-2-wrapper" 
             class="max-h-0 opacity-0 overflow-hidden transition-all duration-500 ease-in-out">
            <div id="step-2-container" 
                 class="mb-6 p-6 border border-gray-200 dark:border-gray-700 rounded-xl bg-white dark:bg-gray-800 shadow-lg">
                <label class="flex items-center text-xl font-extrabold mb-3 text-[#A00001] dark:text-red-400">
                    <span class="mr-3 bg-[#A00001] dark:bg-red-600 text-white w-8 h-8 flex items-center justify-center rounded-full text-lg">2</span>
                    Selecione a Data
                </label>
                <div id="available-dates-container" class="grid grid-cols-4 md:grid-cols-7 gap-3">
                    </div>
                <input type="hidden" id="selected_date_value" name="date" required disabled> 
            </div>
        </div>

        <div id="step-3-wrapper" 
             class="max-h-0 opacity-0 overflow-hidden transition-all duration-500 ease-in-out">
            <div id="step-3-container" 
                 class="mb-6 p-6 border border-gray-200 dark:border-gray-700 rounded-xl bg-white dark:bg-gray-800 shadow-lg">
                <label class="flex items-center text-xl font-extrabold text-gray-800 dark:text-white mb-3">
                    <span class="mr-3 bg-gray-400 dark:bg-gray-600 text-white w-8 h-8 flex items-center justify-center rounded-full text-lg">3</span>
                    Selecione o Horário (<span id="limit-info" class="">Máx: 1</span>)
                </label>
                <div id="slots-container" class="grid grid-cols-3 md:grid-cols-6 gap-3">
                    </div>
                
                <input type="hidden" id="selected_slot" name="start_time_slots">
                
                <div id="continue-to-step-4" class="flex justify-center mt-6">
                    <button type="button" onclick="goToStep(4)" 
                            class="px-8 py-3 bg-[#A00001] dark:bg-red-700 text-white rounded-lg shadow-xl 
                                   hover:bg-[#7A0000] dark:hover:bg-red-800 transition duration-200 font-extrabold text-lg">
                        Continuar (Passo 4)
                    </button>
                </div>
            </div>
        </div>

        <div id="resume-wrapper" 
            class="max-h-0 mb-3 opacity-0 overflow-hidden transition-all duration-500 ease-in-out">
            <div id="resume-container" 
                 class="p-6 border border-gray-200 dark:border-gray-700 rounded-xl bg-white dark:bg-gray-800 shadow-lg">
                <label class="flex items-center text-xl font-extrabold text-gray-800 dark:text-white mb-3">
                    <span class="mr-3 text-white w-8 h-8 flex items-center justify-center rounded-full text-lg bg-green-600"></span>
                    Resumo da Reserva
                </label>
                <div id="resume-details" class="space-y-3">
                </div>
            </div>
        </div>

        <div id="step-4-wrapper" 
             class="mb-6 max-h-0 opacity-0 overflow-hidden transition-all duration-500 ease-in-out">
            <div id="step-4-container" 
                 class="p-6 border border-gray-200 dark:border-gray-700 rounded-xl bg-white dark:bg-gray-800 shadow-lg">
                <label for="member_id" class="flex items-center text-xl font-extrabold text-gray-800 dark:text-white mb-3">
                    <span class="mr-3 bg-gray-400 dark:bg-gray-600 text-white w-8 h-8 flex items-center justify-center rounded-full text-lg">4</span>
                    Identificação do Sócio
                </label>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div>
                        <input name="member_search_cpf" type="text" id="member_search_cpf" placeholder="CPF do Sócio"
                            class="w-full py-3 px-4 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 text-lg shadow-sm mb-4 bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
                    </div>
                    <div>
                        <input name="member_search_title" type="text" id="member_search_title" placeholder="Matrícula do Sócio"
                        class="w-full py-3 px-4 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 text-lg shadow-sm mb-4 bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
                    </div>
                    <div>
                        <input name="member_search_birthdate" type="date" id="member_search_birthdate" placeholder="Data de Nascimento do Sócio"
                        class="w-full py-3 px-4 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 text-lg shadow-sm mb-4 bg-white dark:bg-gray-900 text-gray-900 dark:text-white dark:[color-scheme:dark]">
                    </div>
                </div>
                <div id="member-selection-info" class="p-3 bg-yellow-50 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-200 rounded-lg hidden">
                    </div>
            </div>
        </div>

        <div id="submit-container" class="flex justify-center mt-8 hidden">
            <button type="button" onclick="goToSubmit()"
                    class="px-8 py-3 bg-green-600 dark:bg-green-700 text-white rounded-lg shadow-xl 
                           hover:bg-green-700 dark:hover:bg-green-600 transition duration-200 font-extrabold text-xl">
                Confirmar e Agendar
            </button>
        </div>
    </form>
</div>


<script>
    let max_limit = 0;
    let selected_options = [];
    const API_TOKEN = "{{ config('services.api.token') }}";
    const API_ALL_MODALITIES = "{{ route('api.placegroup.indexByCategory', 'esportiva') }}";
    const API_AVAILABLE_DATES = "{{ route('schedule.getScheduledDates') }}"; 
    const API_SLOTS = "{{ route('api.schedule.getTimeOptions') }}"; 
    const API_SUBMIT_SCHEDULE = "{{ route('api.schedule.store') }}"; 
    
    // Elementos de UI
    const loadingIndicator = document.getElementById('loading-indicator');
    const quadraSelect = document.getElementById('quadra_id');
    const dateInputHidden = document.getElementById('selected_date_value'); 
    const availableDatesContainer = document.getElementById('available-dates-container'); 
    const slotsContainer = document.getElementById('slots-container');
    const submitContainer = document.getElementById('submit-container');
    const continueButton = document.getElementById('continue-to-step-4');

    // Wrappers e Badges
    const step1Wrapper = document.getElementById('step-1-wrapper');
    const step1Container = document.getElementById('step-1-container');
    const step2Wrapper = document.getElementById('step-2-wrapper');
    const step2Container = document.getElementById('step-2-container');
    const step3Wrapper = document.getElementById('step-3-wrapper');
    const step3Container = document.getElementById('step-3-container');
    const step4Wrapper = document.getElementById('step-4-wrapper');
    const step4Container = document.getElementById('step-4-container');
    const resumeWrapper = document.getElementById('resume-wrapper');
    
    const step2Badge = document.querySelector('#step-2-container span:first-child');
    const step3Badge = document.querySelector('#step-3-container span:first-child');
    const step4Badge = document.querySelector('#step-4-container span:first-child'); 
    const limitInfoSpan = document.getElementById('limit-info');

    const member_search_cpf = document.getElementById('member_search_cpf');
    const member_search_title = document.getElementById('member_search_title');
    const member_search_birthdate = document.getElementById('member_search_birthdate');

    member_search_cpf.addEventListener('input', showSubmitButton);
    member_search_title.addEventListener('input', showSubmitButton);
    member_search_birthdate.addEventListener('input', showSubmitButton);

    const MAX_HEIGHT_PX = '1800px'; 

    // ----------------------------------------------------------------------
    // DESIGN UPDATE - ADAPTADO PARA DARK MODE
    // ----------------------------------------------------------------------
    function updateDesign(step, isActive) {
        let badge, container;
        
        // Definição das classes para facilitar manutenção
        // Estado INATIVO (Modo Claro / Modo Escuro)
        const inactiveContainerClasses = ['border-gray-200', 'bg-white', 'dark:bg-gray-800', 'dark:border-gray-700'];
        const inactiveBadgeClasses = ['bg-gray-400', 'dark:bg-gray-600'];
        
        // Estado ATIVO (Modo Claro / Modo Escuro)
        // dark:bg-transparent dá um tom avermelhado sutil no fundo escuro
        const activeContainerClasses = ['border-2', 'border-[#A00001]', 'dark:border-white', 'bg-[#FFE0E0]', 'dark:bg-transparent', 'step-active'];
        const activeBadgeClasses = ['bg-[#A00001]', 'dark:bg-red-600'];

        // Reseta todos os passos que não são o step 1 para inativo visualmente
        if (step !== 1){
            document.querySelectorAll('.step-active').forEach(el => {
                el.classList.remove(...activeContainerClasses);
                el.classList.add(...inactiveContainerClasses);
            });
        }

        if (step == 1) {
            badge = document.querySelector('#step-1-container span:first-child');
            container = step1Container;
        } else if (step === 2) {
            badge = step2Badge;
            container = step2Container;
        } else if (step === 3) {
            badge = step3Badge;
            container = step3Container;
        } else if (step === 4) {
            badge = step4Badge;
            container = step4Container;
        } else {
            return;
        }

        if (isActive) {
            container.classList.remove(...inactiveContainerClasses);
            container.classList.add(...activeContainerClasses);

            badge.classList.remove(...inactiveBadgeClasses);
            badge.classList.add(...activeBadgeClasses);
            
            if (step === 2) dateInputHidden.disabled = false;
        } else {
            container.classList.remove(...activeContainerClasses);
            container.classList.add(...inactiveContainerClasses);
            
            badge.classList.remove(...activeBadgeClasses);
            badge.classList.add(...inactiveBadgeClasses);
            
            if (step === 2) dateInputHidden.disabled = true;
        }
    }

    function toggleStep(wrapper, open) {
        if (open) {
            wrapper.style.maxHeight = wrapper.scrollHeight + 6 + 'px';
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
    // API CALLS & LOGIC
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
                        const optgroup = document.createElement('optgroup');
                        optgroup.label = modality.category_name || modality.name || 'Outras';
                        // Nota: Optgroups não estilizam bem, mas options sim
                        
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
                    showMessage("error", "Nenhuma quadra disponível.");
                    quadraSelect.innerHTML = '<option value="">Nenhuma opção...</option>';
                }
            })
            .catch(error => {
                showMessage("error", "Erro ao carregar modalidades.");
                console.error(error);
            })
            .finally(() => {
                loadingIndicator.classList.add('hidden');
            });
    }

    quadraSelect.addEventListener('change', function() {
        const quadraId = this.value;
        resetStep(3); 
        
        if (!quadraId) {
            toggleStep(step2Wrapper, false);
            updateDesign(2, true);
            return;
        }

        const selectedOption = quadraSelect.options[quadraSelect.selectedIndex];
        document.getElementById('selected_quadra_name').value = selectedOption.textContent;
        
        // Ajuste visual do label do option (opcional)
        const optgroup = selectedOption.parentElement;
        if(optgroup && !selectedOption.textContent.includes('-')) {
             selectedOption.textContent = `${optgroup.label} - ${selectedOption.textContent}`;
        }

        fetchAvailableDates(quadraId);
        toggleStep(step2Wrapper, true); 
        updateDesign(2, true);
    });
    
    function fetchAvailableDates(quadraId) {
        loadingIndicator.classList.remove('hidden');
        availableDatesContainer.innerHTML = '<span class="dark:text-white">Carregando Datas...</span>';
        
        fetch(`${API_AVAILABLE_DATES}/${quadraId}`) 
            .then(response => response.json())
            .then(data => {
                availableDatesContainer.innerHTML = '';

                if (data.length > 0) {
                    data.forEach(dateStr => {
                        const button = document.createElement('button');
                        button.type = 'button';
                        button.value = dateStr; 
                        button.textContent = formatDateForButton(dateStr); 
                        
                        // CLASSSES ATUALIZADAS PARA DARK MODE
                        // Normal: bg-gray-100 dark:bg-gray-700
                        // Hover: dark:hover:bg-red-600
                        button.className = 'date-button p-2 text-center bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg font-bold shadow-sm transition transform hover:scale-105 hover:bg-[#FF7F7F] dark:hover:bg-red-600 dark:hover:text-white';
                        
                        button.onclick = () => selectDate(button);
                        availableDatesContainer.appendChild(button);
                    });
                    updateDesign(2, true);
                } else {
                    showMessage("error", "Nenhuma data disponível.");
                    updateDesign(2, false); 
                    toggleStep(step2Wrapper, false);
                }
            })
            .catch(error => {
                showMessage("error", "Erro ao carregar datas.");
                updateDesign(2, false);
            })
            .finally(() => {
                loadingIndicator.classList.add('hidden');
                toggleStep(step2Wrapper, true); 
            });
    }

    function selectDate(button) {
        const date = button.value;
        const quadraId = quadraSelect.value;
        
        // 1. Resetar estilos (Normal State)
        document.querySelectorAll('.date-button').forEach(btn => {
            btn.className = 'date-button p-2 text-center bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg font-bold shadow-sm transition transform hover:scale-105 hover:bg-[#FF7F7F] dark:hover:bg-red-600 dark:hover:text-white';
        });
        
        // 2. Aplicar estilo selecionado (Active State)
        // dark:bg-red-700 dark:text-white
        button.className = 'date-button p-2 text-center bg-[#A00001] dark:bg-red-700 text-white rounded-lg font-bold shadow-sm transition transform scale-105';

        dateInputHidden.value = date;

        resetStep(3); 
        fetchSlots(date, quadraId);
    }

    function formatDateForButton(dateString) {
        const parts = dateString.split('-'); 
        return `${parts[2]}/${parts[1]}`; 
    }

    function formatDateForDisplay(dateString) {
        const date = new Date(dateString + 'T00:00:00'); 
        const options = { weekday: 'short', day: '2-digit', month: 'short' };
        return date.toLocaleDateString('pt-BR', options).replace('.', '');
    }

    function resetStep(stepNumber) {
        if (stepNumber === 2) {
            availableDatesContainer.innerHTML = '<span class="dark:text-gray-400">Aguardando seleção da Quadra...</span>';
            toggleStep(step2Wrapper, false);
            updateDesign(2, false); 
            dateInputHidden.disabled = true;
        }
        if (stepNumber === 3) {
            toggleStep(step3Wrapper, false);
            document.getElementById('slots-container').innerHTML = '';
            document.getElementById('selected_slot').value = '';
            updateDesign(3, false);
        }
        document.getElementById('messages').innerHTML = '';
    }

    function fetchSlots(date, quadraId) { 
        loadingIndicator.classList.remove('hidden');
        slotsContainer.innerHTML = '<span class="dark:text-white">Carregando Horários...</span>';
        
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
            max_limit = data.quantity || 1; 
            selected_options = []; 
            limitInfoSpan.textContent = `Máx: ${max_limit}`;
            
            slotsContainer.innerHTML = '';
            continueButton.classList.add('hidden'); 

            if (options.length > 0) {
                options.forEach(slot => {
                    if (slot[2] != 0) return; 
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.value = slot[0] + "," + slot[1]; 
                    button.textContent = slot[0] + " - " + slot[1]; 
                    // CLASSES DARK MODE PARA SLOTS
                    button.className = 'slot-button px-3 py-2 bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg font-bold shadow-sm transition transform hover:scale-105 hover:bg-[#FF7F7F] dark:hover:bg-red-600 dark:hover:text-white';
                    
                    button.onclick = () => selectSlot(button);
                    slotsContainer.appendChild(button);
                });

                updateDesign(3, true);
                toggleStep(step3Wrapper, true); 
            } else {
                showMessage("error", "Nenhum horário disponível.");
                updateDesign(3, false); 
                toggleStep(step3Wrapper, false);
            }
        })
        .catch(error => {
            showMessage("error", "Erro ao buscar horários.");
            console.error(error);
        })
        .finally(() => {
            loadingIndicator.classList.add('hidden');
        });
    }

    function selectSlot(button) {
        const time = button.value; 
        const times = time.split(",");
        const index = selected_options.findIndex(o => o[0] === times[0] && o[1] === times[1]);

        // Base Classes
        const baseClass = 'slot-button px-3 py-2 rounded-lg font-bold transition transform ';
        // Unselected
        const unselectedClass = 'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 shadow-sm hover:scale-105 hover:bg-[#FF7F7F] dark:hover:bg-red-600';
        // Selected
        const selectedClass = 'bg-[#A00001] dark:bg-red-700 text-white shadow-lg scale-105';
        // Marked for removal (hover effect visual logic was weird in original, simplified here)

        if (index !== -1) {
            // Desmarcar
            selected_options.splice(index, 1);
            button.className = baseClass + unselectedClass;
            showMessage("success", `Horário ${times[0]} - ${times[1]} desmarcado.`);
        } else {
            // Marcar
            if (selected_options.length >= max_limit) {
                showMessage("error", `Limite de ${max_limit} seleções atingido.`);
                return;
            }
            selected_options.push([times[0], times[1]]);
            button.className = baseClass + selectedClass;
            showMessage("success", `Horário ${times[0]} - ${times[1]} selecionado.`);
        }

        updateSlotsUI(); 
        resetStep(4);
    }

    function goToStep(nextStep) {
        if (nextStep === 4) {
            if (selected_options.length === 0) {
                showMessage('error', 'Selecione ao menos um horário.');
                return;
            }
            const slotValues = selected_options.map(o => o.join(','));
            document.getElementById('selected_slot').value = slotValues.join(';');

            toggleStep(step1Wrapper, false);
            updateDesign(1, false);
            toggleStep(step2Wrapper, false);
            updateDesign(3, false); // Intencional: Fecha visualmente 2 e 3 mas mantém 3 no lógica se precisar voltar
            toggleStep(step3Wrapper, false);
            
            toggleStep(resumeWrapper, true);
            makeResume();

            toggleStep(step4Wrapper, true);
            updateDesign(4, true);

            continueButton.classList.add('hidden');

        } else if (nextStep === 5) { 
             // ... lógica de submit ...
             document.getElementById('schedule-form').submit();
        }
    }

    function makeResume() {
        const detailsContainer = document.getElementById('resume-details');
        const quadraName = document.getElementById('selected_quadra_name').value;
        const selectedDateDisplay = formatDateForDisplay(document.getElementById('selected_date_value').value);
        
        let html = '';

        // ATUALIZAÇÃO DO TEMPLATE HTML PARA DARK MODE
        // bg-gray-50 -> dark:bg-gray-700
        // border-gray-200 -> dark:border-gray-600
        // text-gray-600 -> dark:text-gray-300
        // Badges: bg-[#FFE0E0] -> dark:bg-red-900/50, text-[#A00001] -> dark:text-red-200

        html += `
            <div class="grid grid-cols-3 gap-4 mb-3 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600">
                <div>
                    <span class="text-sm font-medium text-gray-600 dark:text-gray-300">Local:</span>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <span class="inline-block bg-[#FFE0E0] dark:bg-red-900/50 text-[#A00001] dark:text-red-200 text-sm font-semibold px-3 py-1 rounded-full shadow-sm">${quadraName}</span>
                    </div>
                </div>
                <div>
                    <span class="text-sm font-medium text-gray-600 dark:text-gray-300">Data:</span>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <span class="inline-block bg-[#FFE0E0] dark:bg-red-900/50 text-[#A00001] dark:text-red-200 text-sm font-semibold px-3 py-1 rounded-full shadow-sm">${selectedDateDisplay}</span>
                    </div>
                </div>
                <div>
                <span class="text-sm font-medium text-gray-600 dark:text-gray-300">Horários:</span>
                `;

        selected_options.sort((a, b) => a[0].localeCompare(b[0])); 
        
        const slotBadges = selected_options.map(slot => 
            `<span class="inline-block bg-[#FFE0E0] dark:bg-red-900/50 text-[#A00001] dark:text-red-200 text-sm font-semibold px-3 py-1 rounded-full shadow-sm">
                ${slot[0]} - ${slot[1]}
            </span>`
        ).join(' ');

        html += `<div class="mt-2 flex flex-wrap gap-2">${slotBadges}</div></div>
            </div>`;
        
        detailsContainer.innerHTML = html;
        toggleStep(resumeWrapper, true); 
    }

    function showSubmitButton() {
        if (!member_search_cpf.value || !member_search_title.value || !member_search_birthdate.value) {
            return;
        }
        else if (!TestaCPF(member_search_cpf.value.replace(/\D/g, ''))) {
            showMessage('error', 'CPF inválido.');
            return;
        }
        else if (!TestaData(member_search_birthdate.value)) {
            showMessage('error', 'Data de nascimento inválida.');
            return;
        }
        else 
        {
            submitContainer.classList.remove('hidden');
        }
    }

    function TestaData(data) {
        var dataAtual = new Date();
        var partes = data.split("-");
        var dataInformada = new Date(partes[0], partes[1] - 1, partes[2]);
        if (dataInformada > dataAtual) {
            return false;
        }
        return true;
    }
    function TestaCPF(strCPF) {
        var Soma;
        var Resto;
        Soma = 0;
        if (strCPF == "00000000000") return false;

        for (i=1; i<=9; i++) Soma = Soma + parseInt(strCPF.substring(i-1, i)) * (11 - i);
        Resto = (Soma * 10) % 11;

            if ((Resto == 10) || (Resto == 11))  Resto = 0;
            if (Resto != parseInt(strCPF.substring(9, 10)) ) return false;

        Soma = 0;
        for (i = 1; i <= 10; i++) Soma = Soma + parseInt(strCPF.substring(i-1, i)) * (12 - i);
        Resto = (Soma * 10) % 11;

        if ((Resto == 10) || (Resto == 11))  Resto = 0;
        if (Resto != parseInt(strCPF.substring(10, 11) ) ) return false;
        return true;
    }

    function goToSubmit() {
        if (!document.getElementById('member_search_cpf').value) {
            showMessage('error', 'Selecione um sócio antes de finalizar.');
            return;
        }
        
        // ... (Mesma lógica de fetch do seu código original) ...
        // Apenas reconstruindo o objeto para exemplo
        let member_cpf = document.getElementById('member_search_cpf').value;
        member_cpf = member_cpf.replace(/\D/g, ''); // Remove formatação
        let member_title = document.getElementById('member_search_title').value;
        let member_birthdate = document.getElementById('member_search_birthdate').value;
        let date_val = document.getElementById('selected_date_value').value;
        let place_id = document.getElementById('quadra_id').value;
        
        let body_data = [];
        for (let i = 0; i < selected_options.length; i++) {
            body_data.push({
                cpf: member_cpf,
                title: member_title,
                birthDate: member_birthdate,
                start_schedule: date_val + ' ' + selected_options[i][0],
                end_schedule: date_val + ' ' + selected_options[i][1],
                status_id: 1,
                place_id: place_id,
                price: 0
            });
        }

        fetch(API_SUBMIT_SCHEDULE, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${API_TOKEN}`
            },
            body: JSON.stringify(body_data)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showMessage('success', 'Agendamento realizado!');
                window.location.href = "{{ route('schedule.index') }}";
            } else {
                showMessage('error', data.message || 'Erro ao realizar agendamento. Verifique os dados do sócio.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('error', 'Erro ao realizar agendamento.');
        });
    }

    function updateSlotsUI() {
        if (selected_options.length > 0) {
            continueButton.classList.remove('hidden');
            toggleStep(step3Wrapper, true); 
        } else {
            continueButton.classList.add('hidden');
        }
    }

    function showMessage(type, message) {
        // Implementação básica de mensagem compatível com dark mode
        const msgDiv = document.getElementById('messages');
        const colorClass = type === 'error' ? 'bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-100' : 'bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-100';
        msgDiv.innerHTML = `<div class="p-4 mb-4 rounded-lg ${colorClass}">${message}</div>`;
        setTimeout(() => msgDiv.innerHTML = '', 5000);
    }

    // Inicialização
    document.addEventListener('DOMContentLoaded', function() {
        step2Wrapper.style.maxHeight = '0';
        step3Wrapper.style.maxHeight = '0';
        updateDesign(2, false);
        updateDesign(3, false);
        updateDesign(1, true); // Garante que o passo 1 inicie com o estilo ativo correto
        fetchAllModalities();
    });
</script>