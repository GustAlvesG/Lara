<x-app-layout>

    <style>
        @keyframes pulse-ring { 0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(79,70,229,.4); } 70% { transform: scale(1); box-shadow: 0 0 0 10px rgba(79,70,229,0); } 100% { transform: scale(0.95); } }
        .pulse { animation: pulse-ring 2s infinite; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }
        .fade-in { animation: fadeIn .25s ease-out forwards; }
    </style>

    <div class="max-w-3xl mx-auto py-8 px-4">

        <!-- Header -->
        <div class="mb-8 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <a href="{{ route('company.index') }}" class="p-2 bg-surface rounded-xl shadow-card text-ink-3 hover:text-grena-ink border border-line transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                </a>
                <div>
                    <h1 class="text-2xl font-extrabold text-ink">Monitor de Acesso</h1>
                    <p class="text-sm text-ink-2">Consulte ou registre acessos de Externos terceirizados e freelancers.</p>
                </div>
            </div>
            {{-- O monitor é de todo mundo logado; os atalhos, não — cada um
                 aparece só para quem a rota de destino deixa entrar. --}}
            <div class="flex gap-2">
                @can(\App\Authorization\Permissions::EXTERNOS_CARROS_APLICATIVO)
                <a href="{{ route('company.uber.waiting') }}"
                   class="px-4 py-2 bg-surface border border-grena/40 text-grena-ink rounded-lg font-bold text-sm shadow-card hover:bg-grena-tint transition">
                    Aguardando Motorista
                </a>
                @endcan
                @can(\App\Authorization\Permissions::EXTERNOS_LIBERACAO_PONTUAL)
                <a href="{{ route('company.one-off.index') }}"
                   class="px-4 py-2 bg-surface border border-warn/40 text-warn rounded-lg font-bold text-sm shadow-card hover:bg-warn-soft transition">
                    Liberação Pontual
                </a>
                @endcan
                @can(\App\Authorization\Permissions::EXTERNOS_HISTORICO)
                <a href="{{ route('company.access.logs') }}"
                   class="px-4 py-2 bg-surface border border-line text-ink rounded-lg font-bold text-sm shadow-card hover:bg-subtle transition">
                    Ver Histórico
                </a>
                @endcan
            </div>
        </div>

        <!-- Input Card -->
        <div class="bg-surface rounded-2xl shadow-pop border border-line p-6 mb-6">
            <label class="block text-xs font-bold text-ink-3 uppercase tracking-wider mb-3">CPF do funcionário/freelancer ou nome da empresa</label>
            <div class="flex gap-3">
                <input type="text" id="target-input"
                    placeholder="Ex: 123.456.789-09  ou  Acme Serviços"
                    class="flex-1 px-4 py-3 border border-line rounded-xl focus:ring-2 focus:ring-grena-tint focus:border-grena outline-none transition shadow-card text-base font-medium bg-surface text-ink">
                <button onclick="checkAccess(false)"
                    title="Consulta sem registrar no histórico"
                    class="px-5 py-3 bg-surface border border-line text-ink-2 rounded-xl font-bold text-sm shadow-card hover:bg-subtle transition whitespace-nowrap">
                    Consultar
                </button>
                <button onclick="checkAccess(true)"
                    title="Valida e registra no histórico"
                    class="px-5 py-3 bg-grena text-white rounded-xl font-bold text-sm shadow-card hover:bg-grena-hover transition whitespace-nowrap">
                    Registrar Acesso
                </button>
            </div>
            <p class="mt-2 text-[11px] text-ink-3">
                <span class="font-semibold">Consultar</span> apenas valida sem gravar.
                <span class="font-semibold ml-2">Registrar Acesso</span> valida e grava no histórico.
            </p>
            <p class="mt-1 text-[11px] text-ink-3">
                O CPF também consulta o contrato do freelancer: ele entra a partir de 30 min antes do início do serviço, até o término.
            </p>
            <p class="mt-1 text-[11px] text-ink-3">
                E a <span class="font-semibold">liberação pontual</span> do dia: vale para uma única entrada, gasta ao registrar.
            </p>
        </div>

        <!-- Loading -->
        <div id="loading" class="hidden flex justify-center py-8">
            <div class="w-8 h-8 border-4 border-grena border-t-transparent rounded-full animate-spin"></div>
        </div>

        <!-- Result -->
        <div id="result-area"></div>

        <!-- Session Log -->
        <div id="session-log-wrapper" class="hidden mt-8">
            <h3 class="text-xs font-bold text-ink-3 uppercase tracking-wider mb-3">Consultas desta sessão</h3>
            <div id="session-log" class="space-y-2"></div>
        </div>

    </div>

    <script>
        const sessionLog = [];

        // Linhas do último resultado renderizado. Os botões "Registrar" mandam
        // o índice daqui, e não só o id: terceirizado e freelancer são tabelas
        // diferentes e cada um tem o seu endpoint de registro.
        let currentEntries = [];

        // Cada tipo de resultado grava por um caminho próprio.
        const REGISTER_ENDPOINT = {
            worker:     { url: '/api/company-access/register-worker-access',     key: 'worker_id' },
            freelancer: { url: '/api/company-access/register-freelancer-access', key: 'freelancer_id' },
            one_off:    { url: '/api/company-access/register-one-off-access',    key: 'one_off_access_id' },
        };

        const ONE_OFF_CREATE_URL = @json(route('company.one-off.create'));

        function registerRequest(entry) {
            const endpoint = REGISTER_ENDPOINT[entry.type ?? 'worker'] ?? REGISTER_ENDPOINT.worker;

            return fetch(endpoint.url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ [endpoint.key]: entry.id })
            });
        }

        document.getElementById('target-input').addEventListener('keydown', function (e) {
            if (e.key === 'Enter') checkAccess(true);
        });

        async function checkAccess(register) {
            const target = document.getElementById('target-input').value.trim();
            if (!target) {
                const inp = document.getElementById('target-input');
                inp.classList.add('ring-2', 'ring-grena-tint', 'border-danger/40');
                setTimeout(() => inp.classList.remove('ring-2', 'ring-grena-tint', 'border-danger/40'), 1500);
                return;
            }

            document.getElementById('loading').classList.remove('hidden');
            document.getElementById('result-area').innerHTML = '';

            try {
                // Always validate first — decide on registration after seeing result count
                const res  = await fetch('/api/company-access/validate-access', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ target })
                });
                const data = await res.json();

                if (register && data.found) {
                    if (data.workers.length === 1) {
                        // Single result (CPF search) → register immediately.
                        // Mostra a linha que o registro devolveu: a liberação
                        // pontual pode ter sido gasta entre a consulta e o
                        // registro, e aí o que vale é o "negado" gravado.
                        const reg = await registerRequest(data.workers[0]).then(r => r.json()).catch(() => null);
                        if (data.workers[0].type === 'one_off' && reg && reg.found && reg.workers && reg.workers[0]) {
                            data.workers[0] = { ...data.workers[0], ...reg.workers[0] };
                        }
                        renderResult(data, 'registered', target);
                        sessionLog.unshift({ target, data, register: true, time: new Date() });
                    } else {
                        // Multiple workers (company search) → show per-worker register buttons
                        renderResult(data, 'pending', target);
                        sessionLog.unshift({ target, data, register: false, time: new Date() });
                    }
                } else {
                    renderResult(data, 'consulta', target);
                    sessionLog.unshift({ target, data, register: false, time: new Date() });
                }

                renderSessionLog();

            } catch (err) {
                document.getElementById('result-area').innerHTML = `
                    <div class="fade-in bg-danger-soft border border-danger/40 rounded-2xl p-5 text-danger font-semibold text-sm">
                        Erro de conexão. Verifique o servidor.
                    </div>`;
            } finally {
                document.getElementById('loading').classList.add('hidden');
            }
        }

        // Called by per-row "Registrar" buttons
        async function registerEntry(index, buttonEl) {
            const entry = currentEntries[index];
            if (!entry) return;

            buttonEl.disabled = true;
            buttonEl.textContent = '...';

            try {
                const res  = await registerRequest(entry);
                const data = await res.json();

                if (data.found) {
                    const allowed = data.workers[0].allowed;
                    buttonEl.textContent  = allowed ? '✓ Registrado' : '✗ Registrado';
                    buttonEl.className    = `px-3 py-1.5 rounded-full text-xs font-black ${allowed ? 'bg-ok text-white dark:text-canvas' : 'bg-danger text-white'} cursor-default`;
                    sessionLog.unshift({ target: entry.name, data, register: true, time: new Date() });
                    renderSessionLog();
                }
            } catch {
                buttonEl.textContent = 'Erro';
                buttonEl.disabled    = false;
            }
        }

        function reasonLabel(reason) {
            const map = {
                worker_not_found:  'CPF não encontrado como terceirizado, freelancer nem liberação pontual de hoje.',
                company_not_found: 'Empresa não encontrada no sistema.',
            };
            return map[reason] ?? reason ?? 'Não encontrado.';
        }

        /**
         * Linha de detalhe abaixo do nome. O freelancer sempre se identifica
         * como tal: um mesmo CPF pode voltar como terceirizado E como
         * freelancer, e o cabeçalho só cabe um nome de empresa.
         */
        function entryDetail(w) {
            if (w.type === 'one_off') {
                return oneOffDetail(w);
            }

            if ((w.type ?? 'worker') !== 'freelancer') {
                return '';
            }

            const tag = `<span class="text-[10px] font-black uppercase tracking-wide bg-grena-tint text-grena-ink px-2 py-0.5 rounded-full">Freelancer</span>`;

            if (!w.allowed) {
                return `<div class="flex items-center gap-2 mt-1">${tag}
                            <span class="text-xs text-ink-2">Sem serviço registrado para este horário.</span>
                        </div>`;
            }

            const s = w.service ?? {};
            const parts = [s.window ? `Entrada liberada ${escHtml(s.window)}` : null, s.period ? escHtml(s.period) : null]
                .filter(Boolean).join(' &nbsp;·&nbsp; ');
            const place = [s.function, s.location].filter(Boolean).map(escHtml).join(' &nbsp;·&nbsp; ');

            return `<div class="flex items-center gap-2 mt-1">${tag}
                        <span class="text-xs text-ink-2">${parts}</span>
                    </div>
                    ${place ? `<p class="text-xs text-ink-3 mt-0.5">${place}</p>` : ''}`;
        }

        /**
         * Liberação pontual: sempre com o motivo e quem autorizou à vista —
         * é exceção, e o porteiro precisa saber de onde ela veio.
         */
        function oneOffDetail(w) {
            const o = w.one_off ?? {};
            const tag = `<span class="text-[10px] font-black uppercase tracking-wide bg-warn-soft text-warn px-2 py-0.5 rounded-full">Liberação Pontual</span>`;

            let status;
            if (w.allowed) {
                status = 'Uma entrada, válida só hoje.';
            } else if (w.reason === 'one_off_access_used') {
                status = o.used_at ? `Já utilizada às ${escHtml(o.used_at)}.` : 'Já utilizada.';
            } else {
                status = 'Não está mais disponível.';
            }

            const by = [o.authorized_by ? `Autorizada por ${escHtml(o.authorized_by)}` : 'Autorizada', o.created_at ? `às ${escHtml(o.created_at)}` : null]
                .filter(Boolean).join(' ');

            return `<div class="flex items-center gap-2 mt-1">${tag}
                        <span class="text-xs text-ink-2">${status}</span>
                    </div>
                    ${o.reason ? `<p class="text-xs text-ink-2 mt-1 whitespace-pre-line">${escHtml(o.reason)}</p>` : ''}
                    <p class="text-xs text-ink-3 mt-0.5">${by}</p>`;
        }

        /** Atalho do "não encontrado" para a liberação pontual, já com o CPF. */
        function oneOffShortcut(target) {
            const digits = String(target).replace(/\D/g, '');
            if (digits.length !== 11) {
                return '';
            }

            return `<a href="${ONE_OFF_CREATE_URL}?cpf=${digits}"
                       class="ml-auto shrink-0 px-4 py-2 bg-warn text-white dark:text-canvas rounded-xl font-bold text-sm shadow-card hover:bg-warn/90 transition">
                        Criar liberação pontual
                    </a>`;
        }

        // mode: 'registered' | 'pending' | 'consulta'
        function renderResult(data, mode, target) {
            const area = document.getElementById('result-area');

            if (!data.found) {
                area.innerHTML = `
                    <div class="fade-in bg-surface border border-danger/40 rounded-2xl shadow-card overflow-hidden">
                        <div class="w-full h-1 bg-danger"></div>
                        <div class="p-6 flex items-center gap-4">
                            <div class="w-14 h-14 rounded-full bg-danger-soft flex items-center justify-center shrink-0">
                                <svg class="w-7 h-7 text-danger" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </div>
                            <div>
                                <p class="font-black text-danger text-lg">Não Encontrado</p>
                                <p class="text-sm text-ink-2 mt-0.5">${reasonLabel(data.reason)}</p>
                                <p class="text-xs text-ink-3 mt-1">Alvo: <span class="font-mono font-bold">${escHtml(target)}</span></p>
                            </div>
                            ${data.reason === 'worker_not_found' ? oneOffShortcut(target) : ''}
                        </div>
                    </div>`;
                return;
            }

            const allAllowed = data.workers.every(w => w.allowed);
            const anyAllowed = data.workers.some(w => w.allowed);
            const topColor   = allAllowed ? 'bg-ok' : anyAllowed ? 'bg-warn' : 'bg-danger';

            const showPerWorkerBtn = (mode === 'pending');

            currentEntries = data.workers;

            const workersHtml = data.workers.map((w, index) => {
                const avatar = w.image
                    ? `<img src="${escHtml(w.image)}" class="w-12 h-12 rounded-full object-cover border-2 ${w.allowed ? 'border-ok/40' : 'border-danger/40'} shrink-0">`
                    : `<div class="w-12 h-12 rounded-full ${w.allowed ? 'bg-ok-soft text-ok' : 'bg-danger-soft text-grena-ink'} flex items-center justify-center font-black text-lg shrink-0">${escHtml(w.name.charAt(0).toUpperCase())}</div>`;

                const statusBadge = `<span class="px-3 py-1.5 rounded-full text-xs font-black uppercase tracking-wide ${w.allowed ? 'bg-ok-soft text-ok' : 'bg-danger-soft text-danger'}">${w.allowed ? '✓ Permitido' : '✗ Negado'}</span>`;

                const registerBtn = showPerWorkerBtn
                    ? `<button onclick="registerEntry(${index}, this)"
                            class="px-3 py-1.5 bg-grena text-white rounded-full text-xs font-black hover:bg-grena-hover transition">
                           Registrar
                       </button>`
                    : '';

                return `
                    <div class="flex items-center gap-4 p-4 rounded-xl border ${w.allowed ? 'border-ok/40 bg-ok-soft/50' : 'border-danger/40 bg-grena-tint/60'}">
                        ${avatar}
                        <div class="flex-grow">
                            <p class="font-bold text-ink">${escHtml(w.name)}</p>
                            ${entryDetail(w)}
                        </div>
                        ${statusBadge}
                        ${registerBtn}
                    </div>`;
            }).join('');

            const tagMap = {
                registered: '<span class="text-[10px] font-black uppercase tracking-wide bg-grena-tint text-grena-ink px-2 py-0.5 rounded-full">Registrado</span>',
                pending:    '<span class="text-[10px] font-black uppercase tracking-wide bg-warn-soft text-warn px-2 py-0.5 rounded-full">Selecione o funcionário</span>',
                consulta:   '<span class="text-[10px] font-black uppercase tracking-wide bg-subtle text-ink-2 px-2 py-0.5 rounded-full">Apenas Consulta</span>',
            };

            area.innerHTML = `
                <div class="fade-in bg-surface rounded-2xl shadow-pop border border-line overflow-hidden">
                    <div class="w-full h-1.5 ${topColor}"></div>
                    <div class="px-6 py-4 border-b border-line flex items-center justify-between">
                        <div>
                            <p class="font-black text-ink text-lg">${escHtml(data.company)}</p>
                            <p class="text-xs text-ink-3 mt-0.5">${new Date().toLocaleTimeString('pt-BR')} &nbsp;·&nbsp; ${data.workers.length} resultado(s)</p>
                        </div>
                        <div class="flex items-center gap-3">
                            ${!anyAllowed && !data.workers.some(w => w.type === 'one_off') ? oneOffShortcut(target) : ''}
                            ${tagMap[mode] ?? ''}
                        </div>
                    </div>
                    <div class="p-5 space-y-3">${workersHtml}</div>
                </div>`;
        }

        function renderSessionLog() {
            if (sessionLog.length === 0) return;
            document.getElementById('session-log-wrapper').classList.remove('hidden');
            const container = document.getElementById('session-log');

            container.innerHTML = sessionLog.slice(0, 10).map(entry => {
                const found   = entry.data.found;
                const workers = found ? entry.data.workers : [];
                const allowed = workers.some(w => w.allowed);
                const bg      = !found ? 'bg-subtle text-ink-2' : (allowed ? 'bg-ok-soft text-ok' : 'bg-danger-soft text-danger');
                const label   = !found ? '—' : (allowed ? '✓' : '✗');
                const company = found ? entry.data.company : 'Não encontrado';
                const time    = entry.time.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit', second: '2-digit' });

                return `
                    <div class="flex items-center gap-3 bg-surface border border-line rounded-xl px-4 py-3 shadow-card">
                        <span class="w-7 h-7 rounded-full ${bg} flex items-center justify-center text-xs font-black shrink-0">${label}</span>
                        <span class="font-mono text-sm text-ink flex-1">${escHtml(entry.target)}</span>
                        <span class="text-sm text-ink-2">${escHtml(company)}</span>
                        <span class="text-xs text-ink-3 ml-auto shrink-0">${time}</span>
                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded ${entry.register ? 'bg-grena-tint text-grena-ink' : 'bg-subtle text-ink-3'}">${entry.register ? 'reg' : 'cons'}</span>
                    </div>`;
            }).join('');
        }

        function escHtml(str) {
            const d = document.createElement('div');
            d.appendChild(document.createTextNode(String(str)));
            return d.innerHTML;
        }
    </script>

</x-app-layout>
