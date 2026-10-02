<x-app-layout>

    <style>
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: translateY(0); } }
        .fade-in { animation: fadeIn .2s ease-out forwards; }
    </style>

    <div class="max-w-7xl mx-auto py-8 px-4">

        <!-- Header -->
        <div class="mb-8 flex items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <a href="{{ route('company.access.monitor') }}" class="p-2 bg-surface rounded-xl shadow-card text-ink-3 hover:text-grena-ink border border-line transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                </a>
                <div>
                    <h1 class="text-2xl font-extrabold text-ink">Aguardando Acesso do Motorista</h1>
                    <p class="text-sm text-ink-2">
                        Todos os pedidos prontos, esperando o carro chegar na portaria. Confira com o motorista à sua frente e libere direto — sem depender da placa digitada no WhatsApp.
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-3 shrink-0">
                <label class="flex items-center gap-2 text-xs font-bold text-ink-2 cursor-pointer select-none">
                    <input type="checkbox" id="auto-refresh" checked
                           class="rounded border-line-strong text-grena-ink focus:ring-grena-tint">
                    Atualizar sozinho
                </label>
                <button onclick="window.location.reload()"
                        class="px-4 py-2 bg-surface border border-line text-ink rounded-lg font-bold text-sm shadow-card hover:bg-subtle transition">
                    Atualizar
                </button>
            </div>
        </div>

        @include('companies.uber.partials.tabs', ['active' => 'waiting'])

        <!-- Resumo da fila -->
        <div class="flex flex-wrap items-center gap-4 mb-6 text-xs font-bold">
            <span class="px-3 py-1.5 rounded-full bg-grena-tint text-grena-ink">
                {{ $validos->count() }} na validade
            </span>
            @if($expirados->isNotEmpty())
                <span class="px-3 py-1.5 rounded-full bg-danger-soft text-danger">
                    {{ $expirados->count() }} com validade vencida
                </span>
            @endif
            @if($emPreenchimento->isNotEmpty())
                <span class="px-3 py-1.5 rounded-full bg-warn-soft text-warn">
                    {{ $emPreenchimento->count() }} ainda preenchendo no WhatsApp
                </span>
            @endif
            <span class="ml-auto font-semibold text-ink-3">
                Atualizado às {{ now()->format('H:i:s') }}
            </span>
        </div>

        @php
            $validationColors = [
                \App\Models\UberAccessRequest::MEMBER_VALIDATION_VALIDADO       => 'bg-ok-soft text-ok',
                \App\Models\UberAccessRequest::MEMBER_VALIDATION_NAO_ENCONTRADO => 'bg-warn-soft text-warn',
                \App\Models\UberAccessRequest::MEMBER_VALIDATION_INDISPONIVEL   => 'bg-subtle text-ink-2',
            ];
        @endphp

        @if($validos->isNotEmpty() || $expirados->isNotEmpty() || $emPreenchimento->isNotEmpty())
            <x-search-bar mode="client" target="#fila-uber" id="busca-fila" placeholder="Buscar nome, placa, matrícula ou telefone" class="mb-4" />
        @endif

        <div id="fila-uber">
        @if($validos->isEmpty() && $expirados->isEmpty())
            <div class="bg-surface rounded-2xl shadow-card border border-line py-16 text-center mb-6">
                <p class="text-ink-3 font-medium">Nenhum pedido aguardando motorista no momento.</p>
            </div>
        @endif

        @if($validos->isNotEmpty())
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-8">
                @foreach($validos as $req)
                    @include('companies.uber.partials.waiting-card', ['req' => $req, 'expirado' => false, 'validationColors' => $validationColors])
                @endforeach
            </div>
        @endif

        @if($expirados->isNotEmpty())
            <div class="mb-3 flex items-center gap-3 flex-wrap">
                <h2 class="text-sm font-black text-danger uppercase tracking-wider">Validade vencida</h2>
                <p class="text-xs text-ink-3">
                    Passaram dos 30 minutos e o sistema ainda não fechou. Dá para liberar — o histórico registra que foi fora do prazo.
                </p>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-8">
                @foreach($expirados as $req)
                    @include('companies.uber.partials.waiting-card', ['req' => $req, 'expirado' => true, 'validationColors' => $validationColors])
                @endforeach
            </div>
        @endif

        @if($emPreenchimento->isNotEmpty())
            <div class="mb-3 flex items-center gap-3 flex-wrap">
                <h2 class="text-sm font-black text-warn uppercase tracking-wider">Preenchendo agora no WhatsApp</h2>
                <p class="text-xs text-ink-3">
                    O associado começou o pedido e ainda não terminou. Aparece só para consulta; liberar, só quando o pedido ficar pronto.
                </p>
            </div>
            <div class="bg-surface rounded-2xl shadow-card border border-warn/40 overflow-hidden mb-8">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[700px] text-sm">
                        <thead>
                            <tr class="border-b border-line bg-warn-soft">
                                <th class="px-5 py-3 text-left text-[11px] font-black text-ink-3 uppercase tracking-wider">Início</th>
                                <th class="px-5 py-3 text-left text-[11px] font-black text-ink-3 uppercase tracking-wider">Quem está pedindo</th>
                                <th class="px-5 py-3 text-left text-[11px] font-black text-ink-3 uppercase tracking-wider">Já preencheu</th>
                                <th class="px-5 py-3 text-left text-[11px] font-black text-ink-3 uppercase tracking-wider">Parou em</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach($emPreenchimento as $req)
                                <tr data-search="">
                                    <td class="px-5 py-3 whitespace-nowrap text-ink-2">{{ $req->created_at->format('H:i:s') }}</td>
                                    <td class="px-5 py-3">
                                        <p class="font-semibold text-ink">{{ $req->requester_name ?: ($req->contact_name_whatsapp ?: '—') }}</p>
                                        <p class="text-xs font-mono text-ink-3">{{ $req->contact_phone }}</p>
                                    </td>
                                    <td class="px-5 py-3 text-xs text-ink-2">
                                        {{ collect([
                                            $req->matricula ? 'Matrícula/CPF ' . $req->matricula : null,
                                            $req->club_location,
                                            $req->vehicle_plate,
                                        ])->filter()->implode(' · ') ?: 'Nada ainda' }}
                                    </td>
                                    <td class="px-5 py-3">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold bg-warn-soft text-warn">
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
        </div>{{-- #fila-uber --}}

    </div>

    <script>
        const REGISTER_URL = @json(route('company_access.register_uber_request'));
        const PLATE_URL    = @json(route('company_access.uber_request_plate'));

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
                    buttonEl.className = 'w-full px-5 py-2.5 rounded-xl font-black text-sm bg-ok text-white dark:text-canvas cursor-default';
                    card.classList.add('opacity-60');
                    card.querySelectorAll('[data-plate-edit]').forEach(el => el.remove());
                } else {
                    buttonEl.textContent = registerError(data.reason);
                    buttonEl.className = 'w-full px-5 py-2.5 rounded-xl font-black text-sm bg-danger text-white cursor-default';
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
                el.classList.toggle('text-danger', diff <= 0);
                el.classList.toggle('', diff <= 0);
            });
        }

        tickCountdowns();
        setInterval(tickCountdowns, 1000);

        // ------------------------------------------------------------------
        // Auto-refresh: a fila muda sozinha (pedido novo chegando, cron
        // expirando o vencido), e o porteiro não deveria ter de lembrar de
        // apertar F5. Não recarrega no meio de uma liberação, para o resultado
        // não sumir da tela antes de ser lido.
        // ------------------------------------------------------------------
        setInterval(function () {
            var busca = document.getElementById('busca-fila');
            if (document.getElementById('auto-refresh').checked && !acting && !(busca && busca.value.trim())) {
                window.location.reload();
            }
        }, 30000);
    </script>

</x-app-layout>
