{{--
    Agenda de reservas do dia. Cada local lista os horários (livres, ocupados,
    bloqueados) para a reserva ser feita direto: marcar os horários livres,
    achar o sócio, confirmar. A seleção vale para um local por vez.
--}}
<x-app-layout :bootstrap-grid="false">

    <style>
        /* Horário marcado para a reserva (o JS liga/desliga a classe). */
        .slot-selected {
            background-color: rgb(var(--grena)) !important;
            border-color: rgb(var(--grena)) !important;
            border-style: solid !important;
            color: #fff !important;
            box-shadow: 0 10px 20px -8px rgb(var(--grena) / .45);
        }
        .slot-selected span, .slot-selected p { color: #fff !important; }
        .slot-selected .icon-container { background-color: rgb(255 255 255 / .2) !important; }
        .animate-fadeIn { animation: fadeIn 0.3s ease-out forwards; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    </style>

    <x-page>
        @include('location.partials.header', ['date' => $date])

        @include('partials.alerts')

        <div class="flex flex-wrap items-center justify-between gap-3">
            <x-search-bar mode="client" target="#agenda" placeholder="Buscar local, modalidade ou sócio já agendado" />

            {{-- Legenda: cor e texto juntos. --}}
            <ul class="flex flex-wrap gap-x-4 gap-y-1 text-xs font-bold text-ink-2">
                <li class="flex items-center gap-1.5"><span class="h-3 w-3 rounded border-[1.5px] border-dashed border-line-strong bg-surface"></span> Livre</li>
                <li class="flex items-center gap-1.5"><span class="h-3 w-3 rounded bg-grena"></span> Selecionado</li>
                <li class="flex items-center gap-1.5"><span class="h-3 w-3 rounded bg-ok"></span> Confirmado</li>
                <li class="flex items-center gap-1.5"><span class="h-3 w-3 rounded bg-warn"></span> Pendente</li>
                <li class="flex items-center gap-1.5"><span class="h-3 w-3 rounded bg-ink-3"></span> Bloqueado</li>
            </ul>
        </div>

        <div id="agenda" class="flex flex-col gap-10">
            @forelse($modalities as $modalityName => $places)
            <section class="flex flex-col gap-4">
                @php
                    $group_id = count($places) >= 1 ? $places[0]['group']['id'] : null;
                @endphp
                <a href="{{ $group_id ? route('place-group.show', $group_id) : '#' }}" class="group flex items-center gap-3">
                    <span class="inline-flex items-center gap-2 rounded-full px-3.5 py-1.5 font-display text-sm font-semibold tracking-tight" style="{{ \App\View\AreaColor::style('reservas') }}">
                        <x-icon name="calendar" class="h-4 w-4" /> {{ $modalityName }}
                    </span>
                    <span class="text-xs font-bold text-ink-3">{{ count($places) }} {{ count($places) === 1 ? 'local' : 'locais' }}</span>
                    <span class="h-px flex-grow bg-line"></span>
                    <span class="text-xs font-bold text-ink-3 opacity-0 transition group-hover:opacity-100">Ver modalidade →</span>
                </a>

                <div class="grid grid-cols-1 gap-4">
                    @foreach($places as $place)
                    @php
                        // Imagem salva só com o nome do arquivo mora em public/images.
                        if (isset($place['image']) && $place['image'] && !str_starts_with($place['image'], 'http')) {
                            $place['image'] = asset('images/' . $place['image']);
                        }
                        $available = !empty($place['time_options'] ?? []);
                    @endphp
                    <form id="form-{{ $place['id'] }}" action="{{ route('schedule.store.web') }}" method="POST"
                          data-price="{{ $place['price'] ?? 0 }}" data-search="{{ $modalityName }} {{ $place['name'] }}">
                        @csrf
                        <div class="dados" hidden>
                            <input type="hidden" name="cpf" value="">
                            <input type="hidden" id="selected-member-id-{{ $place['id'] }}" name="title" value="">
                            <input type="hidden" name="birthDate" value="">
                            <input type="hidden" name="date" value="{{ $date }}">
                            <input type="hidden" name="status_id" value="1">
                            <input type="hidden" name="place_id" value="{{ $place['id'] }}">
                            <input type="hidden" name="price" value="">
                        </div>

                        {{-- Sem overflow-hidden no cartão: a lista de sócios encontrados abre
                             para fora dele. Quem arredonda os cantos é a própria imagem. --}}
                        <article class="court-card flex flex-col rounded-card bg-surface shadow-card md:flex-row" data-court-id="{{ $place['id'] }}">
                            <x-media :src="($place['image'] ?? null) ?: null" :alt="$place['name']" area="reservas" icon="calendar" ratio="sq"
                                     class="rounded-t-card md:aspect-auto md:w-60 md:shrink-0 md:rounded-l-card md:rounded-tr-none" />

                            <div class="flex min-w-0 flex-grow flex-col gap-4 p-5">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <h3 class="font-display text-lg font-semibold tracking-tight text-ink">{{ $place['name'] }}</h3>
                                        @if($available)
                                            <x-pill kind="ok" class="mt-1">Disponível</x-pill>
                                        @else
                                            <x-pill kind="off" class="mt-1">Indisponível nesta data</x-pill>
                                        @endif
                                    </div>
                                    <div class="text-right">
                                        <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-ink-3">Por horário</p>
                                        <p class="font-mono text-lg font-semibold text-ink">R$ {{ number_format($place['price'] ?? 0, 2, ',', '.') }}</p>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                                    @unless($available)
                                        <p class="col-span-full text-sm text-ink-3">Nenhum horário disponível para esta data.</p>
                                    @endunless
                                    @foreach(($place['time_options'] ?? []) as $slot)
                                        @include('location.partials.time-card', ['slot' => $slot, 'place' => $place])
                                    @endforeach
                                </div>

                                {{-- Aparece ao marcar um horário: busca do sócio e confirmação. --}}
                                <div id="form-container-{{ $place['id'] }}" class="animate-fadeIn relative z-20 mt-auto hidden border-t border-line pt-4">
                                    <div class="flex flex-col items-start gap-4 md:flex-row md:items-end">
                                        <div class="relative w-full flex-grow">
                                            <label for="member-search-{{ $place['id'] }}" class="mb-1.5 block text-xs font-bold text-ink-2">Sócio (título ou nome)</label>
                                            <div class="relative">
                                                <x-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 h-[18px] w-[18px] -translate-y-1/2 text-ink-3" />
                                                <input type="text"
                                                       id="member-search-{{ $place['id'] }}"
                                                       placeholder="Mínimo 5 caracteres para buscar"
                                                       autocomplete="off"
                                                       oninput="handleMemberSearch(this, '{{ $place['id'] }}')"
                                                       class="h-11 w-full rounded-full border border-line-strong bg-surface pl-10 pr-4 text-ink placeholder:text-ink-3 focus:border-grena focus:ring-4 focus:ring-grena-tint">

                                                {{-- Resultados: uma matrícula pode trazer várias pessoas. --}}
                                                <div id="search-results-{{ $place['id'] }}" class="absolute left-0 right-0 z-50 mt-1 hidden overflow-hidden rounded-2xl border border-line bg-surface shadow-pop">
                                                    <div class="border-b border-line bg-subtle px-3 py-2 text-[11px] font-bold uppercase tracking-[0.08em] text-ink-3">Pessoas encontradas</div>
                                                    <ul class="search-results-list max-h-72 divide-y divide-line overflow-y-auto"></ul>
                                                </div>
                                            </div>

                                            {{-- Sócio escolhido. --}}
                                            <div id="selected-member-tag-{{ $place['id'] }}" class="mt-2 hidden items-center justify-between gap-3 rounded-2xl bg-ok-soft p-3 [&:not(.hidden)]:flex">
                                                <div class="flex items-center gap-3">
                                                    <span class="grid h-8 w-8 place-items-center rounded-full bg-ok text-white dark:text-canvas">
                                                        <x-icon name="check" class="h-4 w-4" />
                                                    </span>
                                                    <div>
                                                        <p class="text-sm font-bold leading-none text-ink" id="selected-member-name-{{ $place['id'] }}"></p>
                                                        <p class="mt-1 text-[11px] font-bold text-ok">Sócio selecionado</p>
                                                    </div>
                                                </div>
                                                <button type="button" onclick="clearMemberSelection('{{ $place['id'] }}')" aria-label="Trocar sócio"
                                                        class="grid h-8 w-8 place-items-center rounded-full text-ink-2 transition hover:bg-surface hover:text-danger">
                                                    <x-icon name="x" class="h-4 w-4" />
                                                </button>
                                            </div>
                                        </div>

                                        <x-primary-button id="submit-btn-{{ $place['id'] }}" disabled class="w-full md:w-auto">
                                            <x-icon name="check" /> Confirmar reserva
                                        </x-primary-button>
                                    </div>
                                    <p class="mt-3 text-xs text-ink-3">
                                        Reserva para: <span id="display-slots-{{ $place['id'] }}" class="font-mono font-semibold text-ink"></span>
                                        <span id="display-total-wrapper-{{ $place['id'] }}" class="hidden">
                                            &middot; Total: <span id="display-total-{{ $place['id'] }}" class="font-mono font-semibold text-ok"></span>
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </article>
                    </form>
                    @endforeach
                </div>
            </section>
            @empty
                <x-empty-state icon="calendar">Nenhuma modalidade com locais cadastrados.</x-empty-state>
            @endforelse
        </div>
    </x-page>

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
                button.setAttribute('aria-pressed', 'false');
                button.querySelector('.status-text').innerText = 'Livre';
                selectedSlots = selectedSlots.filter(s => s !== time);
            } else {
                button.classList.add('slot-selected');
                button.setAttribute('aria-pressed', 'true');
                button.querySelector('.status-text').innerText = 'Selecionado';
                selectedSlots.push(time);
            }
            updateUI(courtId);
        }

        function updateUI(courtId) {
            const formContainer = document.getElementById(`form-container-${courtId}`);
            const inputHidden = document.getElementById(`selected-slots-input-${courtId}`);
            const displaySpan = document.getElementById(`display-slots-${courtId}`);
            const totalWrapper = document.getElementById(`display-total-wrapper-${courtId}`);
            const totalSpan = document.getElementById(`display-total-${courtId}`);

            if (selectedSlots.length > 0) {
                formContainer.classList.remove('hidden');
                selectedSlots.sort();
                // O input JSON é opcional: os horários já vão no POST pelos
                // checkboxes selected_slots[]. Sem a guarda, o erro aqui abortava
                // o resto da função e o resumo da reserva nunca era preenchido.
                if (inputHidden) {
                    inputHidden.value = JSON.stringify(selectedSlots);
                }
                displaySpan.innerText = selectedSlots.join(', ');

                // Total do que será cobrado: cada botão carrega o preço já
                // proporcional ao tempo restante do seu horário (data-price).
                const card = document.querySelector(`.court-card[data-court-id="${courtId}"]`);
                const total = Array.from(card.querySelectorAll('.slot-button.slot-selected'))
                    .reduce((sum, btn) => sum + (parseFloat(btn.dataset.price) || 0), 0);
                totalSpan.innerText = total.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
                totalWrapper.classList.remove('hidden');
            } else {
                formContainer.classList.add('hidden');
                totalWrapper.classList.add('hidden');
                clearMemberSelection(courtId);
                currentCourtId = null;
            }
        }

        function clearAllSelections() {
            document.querySelectorAll('.slot-button').forEach(btn => {
                btn.classList.remove('slot-selected');
                btn.setAttribute('aria-pressed', 'false');
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

            list.innerHTML = '<li class="p-4 text-xs text-ink-3">Pesquisando...</li>';
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
                            list.innerHTML = '<li class="p-4 text-xs font-bold text-danger">Nenhuma pessoa encontrada com esses dados.</li>';
                        } else {
                            list.innerHTML = filtered.map(m => `
                                <li onclick="selectMember('${m.title}', '${m.Name}', '${m.title}', '${placeId}', '${m.document}', '${m.birth_date.split(' ')[0]}')" 
                                    class="group cursor-pointer border-l-4 border-transparent p-3 transition hover:border-grena hover:bg-grena-tint">
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="flex flex-col">
                                            <span class="text-sm font-bold text-ink group-hover:text-grena-ink">${m.Name}</span>
                                            <span class="font-mono text-[11px] text-ink-3">Matrícula ${m.title}</span>
                                        </div>
                                        <span class="rounded-full bg-subtle px-2 py-0.5 text-[10px] font-bold uppercase text-ink-2 transition group-hover:bg-surface">
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
                list.innerHTML = '<li class="p-4 text-xs text-danger">Erro ao conectar com o servidor.</li>';
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

            //Parent Form
            const form = document.getElementById(`form-${placeId}`);
            form.querySelector('input[name="cpf"]').value = cpf;
            form.querySelector('input[name="birthDate"]').value = birthDate;

            // Preço BASE da quadra (por horário). O valor final de cada horário é
            // calculado no servidor: horário já em andamento é cobrado proporcional
            // ao tempo restante, então não dá para fechar o preço aqui.
            // Vem do próprio formulário: antes saía do último local do laço,
            // e toda quadra levava o preço da última.
            form.querySelector('input[name="price"]').value = form.dataset.price || 0;

           
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
            inputSearch.focus();
        }

        function generatePDFTable(modalities) {
    // Verificação de segurança para evitar erro de variável indefinida
    if (!modalities || typeof modalities !== 'object') {
        console.error("Erro: O parâmetro 'modalities' não foi fornecido ou não é um objecto válido.");
        // Opcional: Tentar procurar uma variável global se não for passada por parâmetro
        if (window.modalitiesData) {
            modalities = window.modalitiesData;
        } else {
            alert("Erro ao gerar PDF: Dados de agendamento não encontrados.");
            return;
        }
    }

    const selectedDateInput = document.getElementById('report-date');
    const selectedDate = selectedDateInput ? selectedDateInput.value : new Date().toLocaleDateString('pt-PT');
    
    const printWindow = window.open('', '_blank');
    
    let html = `
        <!DOCTYPE html>
        <html lang="pt-pt">
        <head>
            <meta charset="UTF-8">
            <title>Relatório de Ocupação - ${selectedDate}</title>
            <style>
                @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;700;800&display=swap');
                body { font-family: 'Inter', sans-serif; padding: 40px; color: #1e293b; background: white; }
                .header { display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 4px solid #8A1538; padding-bottom: 20px; margin-bottom: 30px; }
                .header-left h1 { margin: 0; font-size: 28px; font-weight: 800; color: #5C0E26; text-transform: uppercase; letter-spacing: -0.025em; }
                .header-left p { margin: 5px 0 0; font-size: 14px; color: #64748b; font-weight: 600; }
                .header-right { text-align: right; }
                .header-right .date-box { background: #f1f5f9; padding: 10px 20px; border-radius: 12px; display: inline-block; }
                .header-right .date-label { font-size: 10px; font-weight: 800; color: #64748b; text-transform: uppercase; display: block; margin-bottom: 2px; }
                .header-right .date-value { font-size: 18px; font-weight: 800; color: #8A1538; }
                .modality-container { margin-bottom: 40px; page-break-inside: avoid; }
                .modality-header { background: #8A1538; color: white; padding: 12px 20px; border-radius: 8px 8px 0 0; font-weight: 800; text-transform: uppercase; font-size: 14px; display: flex; justify-content: space-between; }
                .court-wrapper { border: 1px solid #e2e8f0; border-top: none; padding: 20px; margin-bottom: 10px; border-radius: 0 0 8px 8px; }
                .court-title { font-size: 16px; font-weight: 700; color: #1e293b; margin-bottom: 12px; display: flex; align-items: center; }
                .court-title::before { content: ""; display: inline-block; width: 4px; height: 16px; background: #8A1538; margin-right: 10px; border-radius: 2px; }
                table { width: 100%; border-collapse: separate; border-spacing: 0; margin-bottom: 20px; }
                th { background-color: #f8fafc; border-bottom: 2px solid #e2e8f0; padding: 10px 15px; text-align: left; font-size: 10px; text-transform: uppercase; color: #64748b; font-weight: 800; }
                td { border-bottom: 1px solid #f1f5f9; padding: 10px 15px; font-size: 12px; color: #334155; }
                .status-pill { padding: 3px 10px; border-radius: 20px; font-size: 9px; font-weight: 800; text-transform: uppercase; display: inline-block; border: 1px solid transparent; }
                .booked { background: #dcfce7; color: #166534; border-color: #bbf7d0; } 
                .pending { background: #fef9c3; color: #854d0e; border-color: #fef08a; } 
                .available { background: #f1f5f9; color: #64748b; border-color: #e2e8f0; }
                .blocked { background: #fee2e2; color: #991b1b; border-color: #fecaca; }
                .member-name { font-weight: 700; color: #1e293b; }
                .member-info { font-size: 10px; color: #64748b; }
                .footer { margin-top: 50px; border-top: 1px solid #e2e8f0; padding-top: 20px; display: flex; justify-content: space-between; font-size: 10px; color: #94a3b8; text-transform: uppercase; }
                @media print { body { padding: 0; } @page { margin: 1.5cm; } }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="header-left"><h1>Clube de Funcionários</h1><p>Relatório de Ocupação de Espaços</p></div>
                <div class="header-right"><div class="date-box"><span class="date-label">Agenda do Dia</span><span class="date-value">${selectedDate}</span></div></div>
            </div>
    `;

    for (const [modName, places] of Object.entries(modalities)) {
        html += `<div class="modality-container"><div class="modality-header"><span>MODALIDADE: ${modName}</span><span>${places.length} LOCAL(IS)</span></div><div class="court-wrapper">`;

        places.forEach(place => {
            html += `<div class="court-title">${place.name}</div><table><thead><tr><th width="20%">Horário</th><th width="20%">Estado</th><th width="60%">Sócio / Observação</th></tr></thead><tbody>`;

            const options = place.time_options || [];
            if (options.length === 0) {
                html += `<tr><td colspan="3" style="text-align:center; padding: 20px; color: #94a3b8; font-style: italic;">Nenhum horário disponível.</td></tr>`;
            } else {
                options.forEach(slot => {
                    let status = 'Livre', statusClass = 'available', detail = '<span style="color: #cbd5e1;">—</span>';

                    if (slot.excluded_by_rule) {
                        status = 'Bloqueado'; statusClass = 'blocked';
                        detail = `<strong>Regra:</strong> ${slot.excluded_by_rule.name || 'Manutenção'}`;
                    } else if (slot.colides) {
                        const isConfirmed = slot.colided_status_id == 1;
                        status = isConfirmed ? 'Confirmado' : 'Pendente';
                        statusClass = isConfirmed ? 'booked' : 'pending';
                        detail = `<div class="member-name">${slot.colided_member?.name || 'N/D'}</div><div class="member-info">Matrícula: ${slot.colided_member?.title || 'N/D'}</div>`;
                    }

                    html += `<tr><td><strong>${slot.start_time}</strong> - ${slot.end_time}</td><td><span class="status-pill ${statusClass}">${status}</span></td><td>${detail}</td></tr>`;
                });
            }
            html += `</tbody></table>`;
        });
        html += `</div></div>`;
    }

    html += `<div class="footer"><span>Gerado automaticamente pelo Sistema 2XKO</span><span>Emissão: ${new Date().toLocaleString('pt-BR')}</span></div></body></html>`;

    printWindow.document.write(html);
    printWindow.document.close();
    setTimeout(() => { printWindow.print(); }, 600);
}
    </script>

</x-app-layout>
