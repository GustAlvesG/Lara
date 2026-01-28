<x-app-layout>

    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f4f7f9; }
        
        /* Estilos para o estado selecionado */
        .slot-selected {
            background-color: #10b981 !important; /* green-500 */
            border-color: #059669 !important;     /* green-600 */
            color: white !important;
            transform: scale(1.02);
            box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.2);
        }
        .slot-selected span, .slot-selected p {
            color: white !important;
        }
        .slot-selected .icon-container {
            background-color: rgba(255, 255, 255, 0.2) !important;
        }
        .slot-selected svg {
            color: white !important;
        }
    </style>

    <div class="max-w-7xl mx-auto pt-8">

        <!-- BOTÕES SUPERIORES DE AÇÃO -->
        @include('location.partials.header', ['date' => $date])

        <!-- LEGENDA -->
        <div class="mb-8 flex flex-wrap gap-6 text-[10px] font-black uppercase tracking-widest text-gray-500">
            <div class="flex items-center gap-2">
                <span class="w-3 h-3 bg-green-600 rounded-full shadow-sm"></span> Confirmado
            </div>
            <div class="flex items-center gap-2">
                <span class="w-3 h-3 bg-yellow-500 rounded-full shadow-sm"></span> Pendente
            </div>
            <div class="flex items-center gap-2">
                <span class="w-3 h-3 bg-indigo-600 rounded-full shadow-sm"></span> Selecionado
            </div>
            <div class="flex items-center gap-2">
                <span class="w-3 h-3 bg-gray-800 rounded-full shadow-sm"></span> Bloqueado
            </div>
        </div>



        <div class="space-y-12">
            @foreach($modalities as $modalityName => $places)
            <section>
                <div class="flex items-center gap-3 mb-6">
                    <span class="px-4 py-1 bg-indigo-100 text-indigo-700 rounded-full text-xs font-black uppercase tracking-widest shadow-sm">
                        {{ $modalityName }}
                    </span>
                    <div class="h-[1px] flex-grow bg-gray-200"></div>
                </div>

                <div class="grid grid-cols-1 gap-8">
                    @foreach($places as $place)
                    <form id="form-{{ $place['id'] }}" action="{{ route('schedule.store.web') }}" method="POST" class="bg-gray-50 p-4 rounded-xl border border-gray-200">
                        @csrf
                        <div class="dados" style="display: none;">
                            <input type="hidden" name="cpf" value="">
                            <input type="hidden" id="selected-member-id-{{ $place['id'] }}" name="title" value="">
                            <input type="hidden" name="birthDate" value="">
                            <input type="hidden" name="date" value="{{ $date }}">
                            <input type="hidden" name="status_id" value="1">
                            <input type="hidden" name="place_id" value="{{ $place['id'] }}">
                            <input type="hidden" name="price" value="">
                        </div>


                        <div class="bg-white rounded-2xl shadow-xl border border-gray-100 court-card" data-court-id="{{ $place['id'] }}">
                            <div class="flex flex-col md:flex-row min-h-full">
                                <div class="md:w-64 h-48 md:h-auto overflow-hidden bg-gray-100 flex-shrink-0">
                                    <img src="{{ $place['image'] ?? 'https://placehold.co/400x300/e2e8f0/475569?text=Sem+Foto' }}" 
                                         alt="{{ $place['name'] }}" class="w-full h-full object-cover">
                                </div>
                                
                                <div class="flex-grow p-6 flex flex-col">
                                    <div class="flex justify-between items-start mb-6">
                                        <div>
                                            <h3 class="text-xl font-extrabold text-gray-900">{{ $place['name'] }}</h3>
                                            <p class="text-xs text-indigo-600 font-bold uppercase tracking-wider">Localidade Disponível</p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-[10px] font-black text-gray-400 uppercase">Preço p/ Hora</p>
                                            <p class="text-lg font-black text-green-600">R$ {{ number_format($place['price'] ?? 0, 2, ',', '.') }}</p>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3 mb-6">
                                        @if(empty($place['time_options'] ?? []))
                                            <p class="text-sm text-gray-500 italic col-span-full">Nenhum horário disponível para esta data.</p>
                                        @endif
                                        @foreach(($place['time_options'] ?? []) as $slot)
                                            @php
                                                $isBlocked = $slot['blocked'] ?? false;
                                                $isBooked = isset($slot['member']) && $slot['member'] !== null;
                                            @endphp
                                            {{-- {{ Array to string }} --}}
                                            @if($isBooked)
                                                <div class="bg-indigo-600 rounded-xl p-3 text-white shadow-md flex flex-col justify-between min-h-[80px] opacity-90">
                                                    <div class="flex justify-between items-start mb-1">
                                                        <span class="text-sm font-black">{{ $slot['start_time'] }}</span>
                                                        <div class="h-5 w-5 rounded bg-white/20 flex items-center justify-center text-[8px] font-bold">
                                                            {{ strtoupper(substr($slot['member']['name'], 0, 2)) }}
                                                        </div>
                                                    </div>
                                                    <p class="text-[10px] font-bold truncate">{{ $slot['member']['name'] }}</p>
                                                </div>
                                            @elseif($isBlocked)
                                                <div class="bg-gray-800 rounded-xl p-3 text-white shadow-md border border-gray-700 flex flex-col justify-between min-h-[80px]">
                                                    <div class="flex justify-between items-start mb-1">
                                                        <span class="text-sm font-black text-gray-400">{{ $slot['start_time'] }}</span>
                                                        <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"></path></svg>
                                                    </div>
                                                    <p class="text-[10px] font-bold text-gray-300 truncate">{{ $slot['rule_to_block'] ?? 'Indisponível' }}</p>
                                                    <p class="text-[9px] text-red-400 font-bold uppercase tracking-widest">Indisponível</p>
                                                </div>
                                            @else
                                                <input value="{{ $slot['start_time']}} - {{ $slot['end_time'] }}" class="hidden" type="checkbox" name="selected_slots[]" id="">
                                                <button type="button" 
                                                   onclick="toggleSlot(this, '{{ $place['id'] }}', '{{ $slot['start_time'] }}')"
                                                   class="slot-button bg-white border-2 border-dashed border-gray-100 rounded-xl p-3 hover:border-green-400 hover:bg-green-50 transition duration-200 flex flex-col justify-between min-h-[80px] text-left">
                                                    <div class="flex justify-between items-start w-full">
                                                        
                                                        <span class="text-sm font-black text-gray-400">{{ $slot['start_time'] }}</span>
                                                        <div class="h-6 w-6 rounded-full bg-gray-50 flex items-center justify-center icon-container transition-colors">
                                                            <svg class="w-3 h-3 text-gray-300 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                                                            </svg>
                                                        </div>
                                                    </div>
                                                    <p class="text-[9px] font-black text-gray-300 uppercase tracking-widest status-text">Livre</p>
                                                </button>

                                                
                                            @endif
                                        @endforeach
                                    </div>

                                    <!-- FORMULÁRIO COM BUSCA DE SÓCIO -->
                                    <div id="form-container-{{ $place['id'] }}" class="hidden animate-fadeIn mt-auto pt-4 border-t border-gray-100">
                                        <div class="flex flex-col md:flex-row items-start gap-4">
                                            <div class="flex-grow w-full relative">
                                                <label class="block text-xs font-black uppercase text-indigo-600 mb-2 tracking-widest">Identificação do Sócio (Título ou Nome)</label>
                                                <div class="relative">
                                                    <input type="text" 
                                                           id="member-search-{{ $place['id'] }}"
                                                           placeholder="Mínimo 5 caracteres para buscar..." 
                                                           oninput="handleMemberSearch(this, '{{ $place['id'] }}')"
                                                           class="w-full px-4 py-3 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none transition shadow-sm font-medium">
                                                    
                                                    <!-- Resultados da busca: Podem aparecer vários por matrícula -->
                                                    <div id="search-results-{{ $place['id'] }}" class="hidden absolute z-50 left-0 right-0 mt-1 bg-white border border-gray-200 rounded-xl shadow-2xl overflow-hidden ring-1 ring-black ring-opacity-5">
                                                        <div class="p-2 bg-gray-50 text-[10px] font-bold text-gray-400 uppercase tracking-widest border-b">Pessoas Encontradas</div>
                                                        <ul class="search-results-list divide-y divide-gray-100">
                                                            <!-- Resultados injetados aqui -->
                                                        </ul>
                                                    </div>
                                                </div>

                                                <!-- Feedback do Membro Selecionado -->
                                                <div id="selected-member-tag-{{ $place['id'] }}" class="hidden mt-2 p-3 bg-green-50 border-2 border-green-100 rounded-xl flex items-center justify-between">
                                                    <div class="flex items-center gap-3">
                                                        <div class="h-8 w-8 rounded-full bg-green-500 flex items-center justify-center text-white font-bold text-xs">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                                        </div>
                                                        <div>
                                                            <p class="text-sm font-black text-green-800 leading-none" id="selected-member-name-{{ $place['id'] }}"></p>
                                                            <p class="text-[10px] text-green-600 font-bold uppercase mt-1">Sócio Selecionado</p>
                                                        </div>
                                                    </div>
                                                    <button type="button" onclick="clearMemberSelection('{{ $place['id'] }}')" class="text-green-400 hover:text-red-500 transition">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                                    </button>
                                                </div>
                                            </div>

                                            <div class="w-full md:w-auto pt-7">
                                                <button type="submit" 
                                                        id="submit-btn-{{ $place['id'] }}"
                                                        disabled
                                                        class="w-full px-8 py-3 bg-gray-300 text-gray-500 rounded-xl font-bold shadow-lg transition cursor-not-allowed uppercase text-sm">
                                                    Confirmar Reserva
                                                </button>
                                            </div>
                                        </div>
                                        <p class="mt-3 text-[10px] text-gray-400 font-medium italic">
                                            Reserva para: <span id="display-slots-{{ $place['id'] }}" class="font-bold text-green-600"></span>
                                        </p>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </form>
                    @endforeach
                </div>
            </section>
            @endforeach
        </div>
    </div>

    <script>
        const API_TOKEN = "{{ config('services.api.token') }}";
        const API_MEMBERS_SEARCH_URL = "{{ route('member.getByTitle') }}";

        let currentCourtId = null;
        let selectedSlots = [];

        function toggleSlot(button, courtId, time) {
            if (currentCourtId !== null && currentCourtId !== courtId) {
                clearAllSelections();
            }
            currentCourtId = courtId;

            checkbox = button.previousElementSibling;
            checkbox.checked = !checkbox.checked;

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
                let mockMembers;
                fetch(API_MEMBERS_SEARCH_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${API_TOKEN}`,
                    },
                    body: JSON.stringify({ title: query })
                })
                .then(response => response.json())
                .then(data => {
                
                    console.log('API response data:', data);
                    const filtered = data.filter(m => 
                        m.Name.toLowerCase().includes(query.toLowerCase()) || 
                        m.title.includes(query)
                    );

                    setTimeout(() => {
                        if (filtered.length === 0) {
                            list.innerHTML = '<li class="p-4 text-xs text-red-500 font-bold">Nenhuma pessoa encontrada com esses dados.</li>';
                        } else {
                            list.innerHTML = filtered.map(m => `
                                <li onclick="selectMember('${m.title}', '${m.Name}', '${m.title}', '${placeId}', '${m.document}', '${m.birth_date.split(' ')[0]}')" 
                                    class="p-3 hover:bg-indigo-50 cursor-pointer transition group border-l-4 border-transparent hover:border-indigo-500">
                                    <div class="flex justify-between items-center">
                                        <div class="flex flex-col">
                                            <span class="font-extrabold text-sm text-gray-800 group-hover:text-indigo-700">${m.Name}</span>
                                            <span class="text-[10px] text-gray-400 font-bold tracking-wider">Matrícula: ${m.title}</span>
                                        </div>
                                        <span class="px-2 py-0.5 rounded bg-gray-100 text-[9px] font-black text-gray-500 uppercase group-hover:bg-indigo-100 group-hover:text-indigo-600 transition">
                                            ${m.Titular == 1 ? 'Titular' : 'Dependente'}
                                        </span>
                                    </div>
                                </li>
                            `).join('');
                        }
                    }, 400);
                    
                });

                // MOCK DE RESPOSTA (Simulando uma matrícula '00000' que possui vários membros)
                console.log('Mock members:', mockMembers);

                

            } catch (err) {
                list.innerHTML = '<li class="p-4 text-xs text-red-500">Erro ao conectar com o servidor.</li>';
            }
        }

        function selectMember(id, name, title, placeId, cpf, birthDate) {
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
        
            //Parent Form
            const form = document.getElementById(`form-${placeId}`);
            form.querySelector('input[name="cpf"]').value = cpf;
            form.querySelector('input[name="birthDate"]').value = birthDate;

            //Calculo do preço = número de horários selecionados * preço da quadra
            const pricePerHour = {{ $place['price'] ?? 0 }};
            // const totalPrice = selectedSlots.length * pricePerHour;
            form.querySelector('input[name="price"]').value = pricePerHour

           
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

        function generatePDFTable() {
            // Captura os dados do PHP para o JS
            const modalities = @json($modalities);
            const selectedDate = document.getElementById('report-date').value;
            
            // Cria uma nova janela para o conteúdo de impressão
            const printWindow = window.open('', '_blank');
            
            let html = `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Relatório de Agendamentos - ${selectedDate}</title>
                    <style>
                        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;700;800&display=swap');
                        
                        body { font-family: 'Inter', sans-serif; padding: 40px; color: #1e293b; background: white; }
                        
                        .header { display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 4px solid #4f46e5; padding-bottom: 20px; margin-bottom: 30px; }
                        .header-left h1 { margin: 0; font-size: 28px; font-weight: 800; color: #1e3a8a; text-transform: uppercase; letter-spacing: -0.025em; }
                        .header-left p { margin: 5px 0 0; font-size: 14px; color: #64748b; font-weight: 600; }
                        .header-right { text-align: right; }
                        .header-right .date-box { background: #f1f5f9; padding: 10px 20px; border-radius: 12px; display: inline-block; }
                        .header-right .date-label { font-size: 10px; font-weight: 800; color: #64748b; text-transform: uppercase; display: block; margin-bottom: 2px; }
                        .header-right .date-value { font-size: 18px; font-weight: 800; color: #4f46e5; }

                        .modality-container { margin-bottom: 40px; page-break-inside: avoid; }
                        .modality-header { background: #4f46e5; color: white; padding: 10px 20px; border-radius: 8px 8px 0 0; font-weight: 800; text-transform: uppercase; font-size: 14px; display: flex; justify-content: space-between; align-items: center; }
                        
                        .court-container { border: 1px solid #e2e8f0; border-top: none; padding: 20px; margin-bottom: 15px; border-radius: 0 0 8px 8px; }
                        .court-title { font-size: 18px; font-weight: 700; color: #1e293b; margin-bottom: 15px; display: flex; align-items: center; }
                        .court-title::before { content: ""; display: inline-block; width: 4px; height: 18px; background: #4f46e5; margin-right: 10px; border-radius: 2px; }
                        
                        table { width: 100%; border-collapse: separate; border-spacing: 0; margin-bottom: 10px; }
                        th { background-color: #f8fafc; border-bottom: 2px solid #e2e8f0; padding: 12px 15px; text-align: left; font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 800; }
                        td { border-bottom: 1px solid #f1f5f9; padding: 12px 15px; font-size: 13px; color: #334155; }
                        tr:last-child td { border-bottom: none; }
                        tr:nth-child(even) { background-color: #fcfdfe; }

                        .status-pill { padding: 4px 10px; border-radius: 20px; font-size: 10px; font-weight: 800; text-transform: uppercase; display: inline-block; border: 1px solid transparent; }
                        .booked { background: #eef2ff; color: #4338ca; border-color: #c7d2fe; }
                        .blocked { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }
                        .available { background: #ecfdf5; color: #065f46; border-color: #a7f3d0; }
                        
                        .member-info { display: flex; align-items: center; gap: 8px; }
                        .member-name { font-weight: 700; color: #1e293b; }
                        
                        .footer { margin-top: 60px; border-top: 1px solid #e2e8f0; padding-top: 20px; display: flex; justify-content: space-between; font-size: 10px; color: #94a3b8; font-weight: 600; text-transform: uppercase; }

                        @media print {
                            body { padding: 0; }
                            @page { margin: 1.5cm; }
                        }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <div class="header-left">
                            <h1>Reservas</h1>
                            <p>Relatório Consolidado de Ocupação</p>
                        </div>
                        <div class="header-right">
                            <div class="date-box">
                                <span class="date-label">Agenda do Dia</span>
                                <span class="date-value">${new Date(selectedDate + 'T00:00:00').toLocaleDateString('pt-PT', {day:'2-digit', month:'long', year:'numeric'})}</span>
                            </div>
                        </div>
                    </div>
            `;

            for (const [modality, courts] of Object.entries(modalities)) {
                html += `<div class="modality-container">
                            <div class="modality-header">
                                <span>Modalidade: ${modality}</span>
                                <span>${courts.length} Local(is)</span>
                            </div>
                            <div class="court-container">`;
                
                courts.forEach(court => {
                    html += `<div class="court-name-wrapper">
                                <div class="court-title">${court.name}</div>
                                <table>
                                    <thead>
                                        <tr>
                                            <th width="20%">Horário</th>
                                            <th width="20%">Estado</th>
                                            <th width="60%">Responsável / Detalhes</th>
                                        </tr>
                                    </thead>
                                    <tbody>`;
                    
                    if(court.time_options && court.time_options.length > 0) {
                        court.time_options.forEach(slot => {
                            let status = 'Livre';
                            let statusClass = 'available';
                            let details = '<span style="color: #94a3b8;">Disponível para agendamento</span>';

                            if (slot.member) {
                                status = 'Agendado';
                                statusClass = 'booked';
                                details = `<div class="member-info"><span class="member-name">${slot.member.name}</span></div>`;
                            } else if (slot.blocked) {
                                status = 'Bloqueado';
                                statusClass = 'blocked';
                                details = `<strong>Motivo:</strong> ${slot.rule_to_block || 'Bloqueio Administrativo'}`;
                            }

                            html += `<tr>
                                        <td><strong>${slot.start_time}</strong> — ${slot.end_time || ''}</td>
                                        <td><span class="status-pill ${statusClass}">${status}</span></td>
                                        <td>${details}</td>
                                    </tr>`;
                        });
                    } else {
                        html += `<tr><td colspan="3" style="text-align:center; padding: 30px; color:#94a3b8; font-style: italic;">Nenhum horário configurado para este local.</td></tr>`;
                    }
                    
                    html += `</tbody></table></div>`;
                });
                
                html += `</div></div>`;
            }

            html += `
                <div class="footer">
                    <span>Gerado pelo LARA</span>
                    <span>Emissão: ${new Date().toLocaleString('pt-PT')}</span>
                    <span>Página 1 de 1</span>
                </div>
                </body></html>`;

            printWindow.document.write(html);
            printWindow.document.close();
            
            setTimeout(() => {
                printWindow.print();
            }, 600);
        }
    </script>

    <style>
        .animate-fadeIn { animation: fadeIn 0.3s ease-out forwards; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</x-app-layout>