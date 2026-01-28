let currentCourtId = null;
let selectedSlots = [];

function toggleSlot(button, courtId, time) {
    if (currentCourtId !== null && currentCourtId !== courtId) {
        clearAllSelections();
    }
    currentCourtId = courtId;

    if (button.classList.contains('slot-selected')) {
        button.classList.remove('slot-selected');
        button.querySelector('.status-text').innerText = 'Livre';
        selectedSlots = selectedSlots.filter(s => s !== time);
    } else {
        button.classList.add('slot-selected');
        button.querySelector('.status-text').innerText = 'Selecionado';
        selectedSlots.push(time);
    }
    updateUI(courtId);
}

function updateUI(courtId) {
    const formContainer = document.getElementById(`form-container-${courtId}`);
    const inputHidden = document.getElementById(`selected-slots-input-${courtId}`);
    const displaySpan = document.getElementById(`display-slots-${courtId}`);

    if (selectedSlots.length > 0) {
        formContainer.classList.remove('hidden');
        selectedSlots.sort();
        inputHidden.value = JSON.stringify(selectedSlots);
        displaySpan.innerText = selectedSlots.join(', ');
    } else {
        formContainer.classList.add('hidden');
        clearMemberSelection(courtId);
        currentCourtId = null;
    }
}

function clearAllSelections() {
    document.querySelectorAll('.slot-button').forEach(btn => {
        btn.classList.remove('slot-selected');
        btn.querySelector('.status-text').innerText = 'Livre';
    });
    document.querySelectorAll('[id^="form-container-"]').forEach(container => {
        container.classList.add('hidden');
        const placeId = container.id.replace('form-container-', '');
        clearMemberSelection(placeId);
    });
    selectedSlots = [];
}

// LÓGICA DE BUSCA DE MEMBROS (Lidando com múltiplas pessoas por matrícula)
async function handleMemberSearch(input, placeId) {
    const query = input.value;
    const resultsBox = document.getElementById(`search-results-${placeId}`);
    const list = resultsBox.querySelector('ul');


    if (query.length < 5) {
        resultsBox.classList.add('hidden');
        return;
    }

    list.innerHTML = '<li class="p-4 text-xs text-gray-500 italic">Pesquisando no banco de dados...</li>';
    resultsBox.classList.remove('hidden');

    try {

        fetch('/api/member/by-title', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${API_TOKEN}`,
            },
            body: JSON.stringify({ title: query })
        });

        // MOCK DE RESPOSTA (Simulando uma matrícula '00000' que possui vários membros)
        const memberDatabase = [
            { id: 101, name: "Erick Ribeiro Porto", title: "00000", type: "Titular" },
            { id: 102, name: "Maria Eduarda Porto", title: "00000", type: "Dependente" },
            { id: 103, name: "Lucas Porto", title: "00000", type: "Dependente" },
            { id: 104, name: "João Pedro Santos", title: "20991", type: "Titular" }
        ];

        const filtered = memberDatabase.filter(m => 
            m.name.toLowerCase().includes(query.toLowerCase()) || 
            m.title.includes(query)
        );

        setTimeout(() => {
            if (filtered.length === 0) {
                list.innerHTML = '<li class="p-4 text-xs text-red-500 font-bold">Nenhuma pessoa encontrada com esses dados.</li>';
            } else {
                list.innerHTML = filtered.map(m => `
                    <li onclick="selectMember('${m.id}', '${m.name}', '${m.title}', '${placeId}')" 
                        class="p-3 hover:bg-indigo-50 cursor-pointer transition group border-l-4 border-transparent hover:border-indigo-500">
                        <div class="flex justify-between items-center">
                            <div class="flex flex-col">
                                <span class="font-extrabold text-sm text-gray-800 group-hover:text-indigo-700">${m.name}</span>
                                <span class="text-[10px] text-gray-400 font-bold uppercase tracking-wider">Matrícula: ${m.title}</span>
                            </div>
                            <span class="px-2 py-0.5 rounded bg-gray-100 text-[9px] font-black text-gray-500 uppercase group-hover:bg-indigo-100 group-hover:text-indigo-600 transition">
                                ${m.type}
                            </span>
                        </div>
                    </li>
                `).join('');
            }
        }, 400);

    } catch (err) {
        list.innerHTML = '<li class="p-4 text-xs text-red-500">Erro ao conectar com o servidor.</li>';
    }
}

function selectMember(id, name, title, placeId) {
    const resultsBox = document.getElementById(`search-results-${placeId}`);
    const inputSearch = document.getElementById(`member-search-${placeId}`);
    const idHidden = document.getElementById(`selected-member-id-${placeId}`);
    const tag = document.getElementById(`selected-member-tag-${placeId}`);
    const nameDisplay = document.getElementById(`selected-member-name-${placeId}`);
    const submitBtn = document.getElementById(`submit-btn-${placeId}`);

    idHidden.value = id;
    nameDisplay.innerText = `${name} (${title})`;
    
    resultsBox.classList.add('hidden');
    inputSearch.classList.add('hidden');
    tag.classList.remove('hidden');

    submitBtn.disabled = false;
    submitBtn.classList.remove('bg-gray-300', 'text-gray-500', 'cursor-not-allowed');
    submitBtn.classList.add('bg-green-600', 'text-white', 'hover:bg-green-700', 'cursor-pointer');
}

function clearMemberSelection(placeId) {
    const inputSearch = document.getElementById(`member-search-${placeId}`);
    const idHidden = document.getElementById(`selected-member-id-${placeId}`);
    const tag = document.getElementById(`selected-member-tag-${placeId}`);
    const submitBtn = document.getElementById(`submit-btn-${placeId}`);

    if(!inputSearch) return;

    idHidden.value = "";
    inputSearch.value = "";
    inputSearch.classList.remove('hidden');
    tag.classList.add('hidden');

    submitBtn.disabled = true;
    submitBtn.classList.add('bg-gray-300', 'text-gray-500', 'cursor-not-allowed');
    submitBtn.classList.remove('bg-green-600', 'text-white', 'hover:bg-green-700', 'cursor-pointer');
    inputSearch.focus();
}