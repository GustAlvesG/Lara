<x-app-layout>

    <style>
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: translateY(0); } }
        .fade-in { animation: fadeIn .2s ease-out forwards; }
    </style>

    <div class="max-w-7xl mx-auto py-8 px-4">

        <!-- Header -->
        <div class="mb-8 flex items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <a href="{{ route('company.access.monitor') }}" class="p-2 bg-white dark:bg-gray-800 rounded-xl shadow-md text-gray-400 dark:text-gray-500 hover:text-indigo-600 dark:hover:text-indigo-400 border border-gray-100 dark:border-gray-700 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                </a>
                <div>
                    <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white">Aguardando Acesso do Motorista</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Pedidos prontos, esperando o carro chegar na portaria. Ache pelo nome, placa ou print e libere direto — sem depender da placa digitada no WhatsApp.
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-3 shrink-0">
                <label class="flex items-center gap-2 text-xs font-bold text-gray-500 dark:text-gray-400 cursor-pointer select-none">
                    <input type="checkbox" id="auto-refresh" checked
                           class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500">
                    Atualizar sozinho
                </label>
                <button onclick="window.location.reload()"
                        class="px-4 py-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg font-bold text-sm shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                    Atualizar
                </button>
            </div>
        </div>

        @include('companies.uber.partials.tabs', ['active' => 'waiting'])

        <!-- Busca instantânea -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-5 mb-6">
            <label class="block text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-2">Procurar na fila</label>
            <input type="text" id="filter-input" autofocus autocomplete="off"
                   placeholder="Nome, placa, matrícula/CPF, telefone ou local — filtra enquanto você digita"
                   class="w-full px-4 py-3 border border-gray-200 dark:border-gray-600 rounded-xl text-base font-medium outline-none focus:ring-2 focus:ring-indigo-400 bg-white dark:bg-gray-700 text-gray-900 dark:text-white dark:placeholder-gray-500">
            <div class="flex flex-wrap items-center gap-4 mt-3 text-xs font-semibold">
                <span class="text-indigo-600 dark:text-indigo-400">{{ $validos->count() }} na validade</span>
                @if($expirados->isNotEmpty())
                    <span class="text-red-600 dark:text-red-400">{{ $expirados->count() }} com validade vencida</span>
                @endif
                @if($emPreenchimento->isNotEmpty())
                    <span class="text-amber-600 dark:text-amber-400">{{ $emPreenchimento->count() }} ainda preenchendo no WhatsApp</span>
                @endif
                <span id="filter-count" class="ml-auto text-gray-400 dark:text-gray-500 hidden"></span>
            </div>
        </div>

        @php
            $validationColors = [
                \App\Models\UberAccessRequest::MEMBER_VALIDATION_VALIDADO       => 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400',
                \App\Models\UberAccessRequest::MEMBER_VALIDATION_NAO_ENCONTRADO => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400',
                \App\Models\UberAccessRequest::MEMBER_VALIDATION_INDISPONIVEL   => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
            ];
        @endphp

        @if($validos->isEmpty() && $expirados->isEmpty())
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 py-16 text-center mb-6">
                <p class="text-gray-400 dark:text-gray-500 font-medium">Nenhum pedido aguardando motorista no momento.</p>
            </div>
        @endif

        @if($validos->isNotEmpty())
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-8" data-group>
                @foreach($validos as $req)
                    @include('companies.uber.partials.waiting-card', ['req' => $req, 'expirado' => false, 'validationColors' => $validationColors])
                @endforeach
            </div>
        @endif

        @if($expirados->isNotEmpty())
            <div class="mb-3 flex items-center gap-3 flex-wrap" data-group-header>
                <h2 class="text-sm font-black text-red-600 dark:text-red-400 uppercase tracking-wider">Validade vencida</h2>
                <p class="text-xs text-gray-400 dark:text-gray-500">
                    Passaram dos 30 minutos e o sistema ainda não fechou. Dá para liberar — o histórico registra que foi fora do prazo.
                </p>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-8" data-group>
                @foreach($expirados as $req)
                    @include('companies.uber.partials.waiting-card', ['req' => $req, 'expirado' => true, 'validationColors' => $validationColors])
                @endforeach
            </div>
        @endif

        @if($emPreenchimento->isNotEmpty())
            <div class="mb-3 flex items-center gap-3 flex-wrap" data-group-header>
                <h2 class="text-sm font-black text-amber-600 dark:text-amber-400 uppercase tracking-wider">Preenchendo agora no WhatsApp</h2>
                <p class="text-xs text-gray-400 dark:text-gray-500">
                    O associado começou o pedido e ainda não terminou. Aparece só para consulta; liberar, só quando o pedido ficar pronto.
                </p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-amber-100 dark:border-amber-900/40 overflow-hidden mb-8" data-group>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[700px] text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-700 bg-amber-50/60 dark:bg-amber-900/10">
                                <th class="px-5 py-3 text-left text-[11px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-wider">Início</th>
                                <th class="px-5 py-3 text-left text-[11px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-wider">Quem está pedindo</th>
                                <th class="px-5 py-3 text-left text-[11px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-wider">Já preencheu</th>
                                <th class="px-5 py-3 text-left text-[11px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-wider">Parou em</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 dark:divide-gray-700">
                            @foreach($emPreenchimento as $req)
                                <tr data-row data-search="{{ Str::lower(collect([$req->requester_name, $req->matricula, $req->vehicle_plate, $req->contact_phone, $req->contact_name_whatsapp, $req->club_location])->filter()->implode(' ')) }}">
                                    <td class="px-5 py-3 whitespace-nowrap text-gray-600 dark:text-gray-400">{{ $req->created_at->format('H:i:s') }}</td>
                                    <td class="px-5 py-3">
                                        <p class="font-semibold text-gray-700 dark:text-gray-300">{{ $req->requester_name ?: ($req->contact_name_whatsapp ?: '—') }}</p>
                                        <p class="text-xs font-mono text-gray-400 dark:text-gray-500">{{ $req->contact_phone }}</p>
                                    </td>
                                    <td class="px-5 py-3 text-xs text-gray-500 dark:text-gray-400">
                                        {{ collect([
                                            $req->matricula ? 'Matrícula/CPF ' . $req->matricula : null,
                                            $req->club_location,
                                            $req->vehicle_plate,
                                        ])->filter()->implode(' · ') ?: 'Nada ainda' }}
                                    </td>
                                    <td class="px-5 py-3">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400">
                                            {{ $req->statusLabel() }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <div id="empty-filter" class="hidden bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 py-12 text-center">
            <p class="text-gray-400 dark:text-gray-500 font-medium">Nada na fila corresponde a essa busca.</p>
        </div>

    </div>

    <script>
        const REGISTER_URL = @json(route('company_access.register_uber_request'));
        const PLATE_URL    = @json(route('company_access.uber_request_plate'));

        // ------------------------------------------------------------------
        // Busca instantânea. A fila é curta (30 min de validade) e já veio
        // inteira no HTML, então filtrar aqui é mais rápido — e mais honesto —
        // do que ida e volta ao servidor a cada tecla.
        // ------------------------------------------------------------------
        const filterInput = document.getElementById('filter-input');

        function applyFilter() {
            const term = filterInput.value.trim().toLowerCase();
            const compact = term.replace(/[^a-z0-9]/g, '');
            let visible = 0;

            document.querySelectorAll('[data-row]').forEach(row => {
                const haystack = row.dataset.search || '';
                const hit = term === ''
                    || haystack.includes(term)
                    || (compact !== '' && haystack.replace(/[^a-z0-9]/g, '').includes(compact));

                row.classList.toggle('hidden', !hit);
                if (hit) visible++;
            });

            // Um bloco sem nenhuma linha visível some junto com o seu título.
            document.querySelectorAll('[data-group]').forEach(group => {
                const anyVisible = group.querySelectorAll('[data-row]:not(.hidden)').length > 0;
                group.classList.toggle('hidden', !anyVisible);

                const header = group.previousElementSibling;
                if (header && header.hasAttribute('data-group-header')) {
                    header.classList.toggle('hidden', !anyVisible);
                }
            });

            const count = document.getElementById('filter-count');
            count.textContent = term === '' ? '' : visible + ' resultado(s)';
            count.classList.toggle('hidden', term === '');

            const total = document.querySelectorAll('[data-row]').length;
            document.getElementById('empty-filter').classList.toggle('hidden', !(term !== '' && visible === 0 && total > 0));
        }

        filterInput.addEventListener('input', applyFilter);

        // ------------------------------------------------------------------
        // Liberar pelo id do pedido. É o ponto da tela: quem escolheu ESTA
        // linha foi o porteiro, com o carro à vista — então a placa digitada
        // no WhatsApp não entra na decisão.
        // ------------------------------------------------------------------
        let acting = false;

        async function liberar(id, buttonEl) {
            const card = buttonEl.closest('[data-row]');

            if (!confirm('Liberar o acesso deste pedido? O associado recebe o aviso de que o carro chegou.')) {
                return;
            }

            acting = true;
            buttonEl.disabled = true;
            buttonEl.textContent = 'Liberando...';

            try {
                const res = await fetch(REGISTER_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ uber_access_request_id: id })
                });
                const data = await res.json();

                if (data.found) {
                    buttonEl.textContent = '✓ Liberado';
                    buttonEl.className = 'w-full px-5 py-2.5 rounded-xl font-black text-sm bg-green-600 text-white cursor-default';
                    card.classList.add('opacity-60');
                    card.querySelectorAll('[data-plate-edit]').forEach(el => el.remove());
                } else {
                    buttonEl.textContent = registerError(data.reason);
                    buttonEl.className = 'w-full px-5 py-2.5 rounded-xl font-black text-sm bg-red-600 text-white cursor-default';
                }
            } catch (e) {
                buttonEl.textContent = 'Erro de conexão — tente de novo';
                buttonEl.disabled = false;
            } finally {
                acting = false;
            }
        }

        function registerError(reason) {
            const map = {
                uber_request_not_found:   'Pedido não encontrado',
                uber_request_not_waiting: 'O pedido já saiu da fila — atualize a tela',
            };
            return map[reason] || 'Não foi possível liberar';
        }

        // ------------------------------------------------------------------
        // Correção da placa: o campo que mais chega errado do WhatsApp, e o
        // único que o porteiro confere com o carro na frente dele.
        // ------------------------------------------------------------------
        async function corrigirPlaca(id, atual) {
            const nova = prompt('Placa correta do veículo:', atual || '');

            if (nova === null || nova.trim() === '') {
                return;
            }

            acting = true;

            try {
                const res = await fetch(PLATE_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ uber_access_request_id: id, plate: nova })
                });
                const data = await res.json();

                if (data.found) {
                    window.location.reload();
                } else if (data.reason === 'invalid_plate') {
                    alert('Placa fora do formato esperado (ABC1234 ou ABC1D23).');
                } else {
                    alert('O pedido já saiu da fila. Atualize a tela.');
                }
            } catch (e) {
                alert('Erro de conexão ao salvar a placa.');
            } finally {
                acting = false;
            }
        }

        // ------------------------------------------------------------------
        // Contagem regressiva da validade, a cada segundo.
        // ------------------------------------------------------------------
        function tickCountdowns() {
            document.querySelectorAll('[data-expires]').forEach(el => {
                const diff = new Date(el.dataset.expires).getTime() - Date.now();
                const mins = Math.floor(Math.abs(diff) / 60000);
                const secs = Math.floor((Math.abs(diff) % 60000) / 1000);
                const clock = mins + 'min ' + String(secs).padStart(2, '0') + 's';

                el.textContent = diff > 0 ? 'expira em ' + clock : 'vencido há ' + clock;
                el.classList.toggle('text-red-600', diff <= 0);
                el.classList.toggle('dark:text-red-400', diff <= 0);
            });
        }

        tickCountdowns();
        setInterval(tickCountdowns, 1000);

        // ------------------------------------------------------------------
        // Auto-refresh. Não recarrega enquanto o porteiro está digitando na
        // busca ou no meio de uma liberação — perder o que ele escreveu é pior
        // do que a tela ficar 30s velha.
        // ------------------------------------------------------------------
        setInterval(function () {
            const on = document.getElementById('auto-refresh').checked;
            if (on && !acting && filterInput.value.trim() === '' && document.activeElement !== filterInput) {
                window.location.reload();
            }
        }, 30000);
    </script>

</x-app-layout>
