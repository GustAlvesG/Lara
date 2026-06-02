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
                <a href="{{ route('company.index') }}" class="p-2 bg-white dark:bg-gray-800 rounded-xl shadow-md text-gray-400 dark:text-gray-500 hover:text-indigo-600 dark:hover:text-indigo-400 border border-gray-100 dark:border-gray-700 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                </a>
                <div>
                    <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white">Monitor de Acesso</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Consulte ou registre acessos de parceiros terceirizados.</p>
                </div>
            </div>
            <a href="{{ route('company.access.logs') }}"
               class="px-4 py-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg font-bold text-sm shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                Ver Histórico
            </a>
        </div>

        <!-- Input Card -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 p-6 mb-6">
            <label class="block text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-3">CPF do funcionário ou nome da empresa</label>
            <div class="flex gap-3">
                <input type="text" id="target-input"
                    placeholder="Ex: 123.456.789-09  ou  Acme Serviços"
                    class="flex-1 px-4 py-3 border border-gray-200 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition shadow-sm text-base font-medium bg-white dark:bg-gray-700 text-gray-900 dark:text-white dark:placeholder-gray-400">
                <button onclick="checkAccess(false)"
                    title="Consulta sem registrar no histórico"
                    class="px-5 py-3 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-300 rounded-xl font-bold text-sm shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600 transition whitespace-nowrap">
                    Consultar
                </button>
                <button onclick="checkAccess(true)"
                    title="Valida e registra no histórico"
                    class="px-5 py-3 bg-indigo-600 text-white rounded-xl font-bold text-sm shadow-md hover:bg-indigo-700 transition whitespace-nowrap">
                    Registrar Acesso
                </button>
            </div>
            <p class="mt-2 text-[11px] text-gray-400 dark:text-gray-500">
                <span class="font-semibold">Consultar</span> apenas valida sem gravar.
                <span class="font-semibold ml-2">Registrar Acesso</span> valida e grava no histórico.
            </p>
        </div>

        <!-- Loading -->
        <div id="loading" class="hidden flex justify-center py-8">
            <div class="w-8 h-8 border-4 border-indigo-600 border-t-transparent rounded-full animate-spin"></div>
        </div>

        <!-- Result -->
        <div id="result-area"></div>

        <!-- Session Log -->
        <div id="session-log-wrapper" class="hidden mt-8">
            <h3 class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-3">Consultas desta sessão</h3>
            <div id="session-log" class="space-y-2"></div>
        </div>

    </div>

    <script>
        const sessionLog = [];

        document.getElementById('target-input').addEventListener('keydown', function (e) {
            if (e.key === 'Enter') checkAccess(true);
        });

        async function checkAccess(register) {
            const target = document.getElementById('target-input').value.trim();
            if (!target) {
                const inp = document.getElementById('target-input');
                inp.classList.add('ring-2', 'ring-red-300', 'border-red-300');
                setTimeout(() => inp.classList.remove('ring-2', 'ring-red-300', 'border-red-300'), 1500);
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
                        // Single worker (CPF search) → register immediately
                        await fetch('/api/company-access/register-worker-access', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                            body: JSON.stringify({ worker_id: data.workers[0].id })
                        });
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
                    <div class="fade-in bg-red-50 border border-red-100 rounded-2xl p-5 text-red-700 font-semibold text-sm">
                        Erro de conexão. Verifique o servidor.
                    </div>`;
            } finally {
                document.getElementById('loading').classList.add('hidden');
            }
        }

        // Called by per-worker "Registrar" buttons
        async function registerWorker(workerId, workerName, buttonEl) {
            buttonEl.disabled = true;
            buttonEl.textContent = '...';

            try {
                const res  = await fetch('/api/company-access/register-worker-access', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ worker_id: workerId })
                });
                const data = await res.json();

                if (data.found) {
                    const allowed = data.workers[0].allowed;
                    buttonEl.textContent  = allowed ? '✓ Registrado' : '✗ Registrado';
                    buttonEl.className    = `px-3 py-1.5 rounded-full text-xs font-black ${allowed ? 'bg-green-600 text-white' : 'bg-red-600 text-white'} cursor-default`;
                    sessionLog.unshift({ target: workerName, data, register: true, time: new Date() });
                    renderSessionLog();
                }
            } catch {
                buttonEl.textContent = 'Erro';
                buttonEl.disabled    = false;
            }
        }

        function reasonLabel(reason) {
            const map = {
                worker_not_found:  'Funcionário não encontrado no sistema.',
                company_not_found: 'Empresa não encontrada no sistema.',
            };
            return map[reason] ?? reason ?? 'Não encontrado.';
        }

        // mode: 'registered' | 'pending' | 'consulta'
        function renderResult(data, mode, target) {
            const area = document.getElementById('result-area');

            if (!data.found) {
                area.innerHTML = `
                    <div class="fade-in bg-white border border-red-100 rounded-2xl shadow-sm overflow-hidden">
                        <div class="w-full h-1 bg-red-500"></div>
                        <div class="p-6 flex items-center gap-4">
                            <div class="w-14 h-14 rounded-full bg-red-100 flex items-center justify-center shrink-0">
                                <svg class="w-7 h-7 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </div>
                            <div>
                                <p class="font-black text-red-700 text-lg">Não Encontrado</p>
                                <p class="text-sm text-gray-500 mt-0.5">${reasonLabel(data.reason)}</p>
                                <p class="text-xs text-gray-400 mt-1">Alvo: <span class="font-mono font-bold">${escHtml(target)}</span></p>
                            </div>
                        </div>
                    </div>`;
                return;
            }

            const allAllowed = data.workers.every(w => w.allowed);
            const anyAllowed = data.workers.some(w => w.allowed);
            const topColor   = allAllowed ? 'bg-green-500' : anyAllowed ? 'bg-yellow-400' : 'bg-red-500';

            const showPerWorkerBtn = (mode === 'pending');

            const workersHtml = data.workers.map(w => {
                const avatar = w.image
                    ? `<img src="${escHtml(w.image)}" class="w-12 h-12 rounded-full object-cover border-2 ${w.allowed ? 'border-green-200' : 'border-red-200'} shrink-0">`
                    : `<div class="w-12 h-12 rounded-full ${w.allowed ? 'bg-green-200 text-green-800' : 'bg-red-200 text-red-800'} flex items-center justify-center font-black text-lg shrink-0">${escHtml(w.name.charAt(0).toUpperCase())}</div>`;

                const statusBadge = `<span class="px-3 py-1.5 rounded-full text-xs font-black uppercase tracking-wide ${w.allowed ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}">${w.allowed ? '✓ Permitido' : '✗ Negado'}</span>`;

                const registerBtn = showPerWorkerBtn
                    ? `<button onclick="registerWorker(${w.id}, '${escHtml(w.name).replace(/'/g, "\\'")}', this)"
                            class="px-3 py-1.5 bg-indigo-600 text-white rounded-full text-xs font-black hover:bg-indigo-700 transition">
                           Registrar
                       </button>`
                    : '';

                return `
                    <div class="flex items-center gap-4 p-4 rounded-xl border ${w.allowed ? 'border-green-100 bg-green-50/40' : 'border-red-100 bg-red-50/40'}">
                        ${avatar}
                        <div class="flex-grow">
                            <p class="font-bold text-gray-900">${escHtml(w.name)}</p>
                        </div>
                        ${statusBadge}
                        ${registerBtn}
                    </div>`;
            }).join('');

            const tagMap = {
                registered: '<span class="text-[10px] font-black uppercase tracking-wide bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded-full">Registrado</span>',
                pending:    '<span class="text-[10px] font-black uppercase tracking-wide bg-yellow-100 text-yellow-700 px-2 py-0.5 rounded-full">Selecione o funcionário</span>',
                consulta:   '<span class="text-[10px] font-black uppercase tracking-wide bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full">Apenas Consulta</span>',
            };

            area.innerHTML = `
                <div class="fade-in bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
                    <div class="w-full h-1.5 ${topColor}"></div>
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                        <div>
                            <p class="font-black text-gray-900 text-lg">${escHtml(data.company)}</p>
                            <p class="text-xs text-gray-400 mt-0.5">${new Date().toLocaleTimeString('pt-BR')} &nbsp;·&nbsp; ${data.workers.length} funcionário(s)</p>
                        </div>
                        ${tagMap[mode] ?? ''}
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
                const bg      = !found ? 'bg-gray-100 text-gray-500' : (allowed ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700');
                const label   = !found ? '—' : (allowed ? '✓' : '✗');
                const company = found ? entry.data.company : 'Não encontrado';
                const time    = entry.time.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit', second: '2-digit' });

                return `
                    <div class="flex items-center gap-3 bg-white border border-gray-100 rounded-xl px-4 py-3 shadow-sm">
                        <span class="w-7 h-7 rounded-full ${bg} flex items-center justify-center text-xs font-black shrink-0">${label}</span>
                        <span class="font-mono text-sm text-gray-700 flex-1">${escHtml(entry.target)}</span>
                        <span class="text-sm text-gray-500">${escHtml(company)}</span>
                        <span class="text-xs text-gray-400 ml-auto shrink-0">${time}</span>
                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded ${entry.register ? 'bg-indigo-100 text-indigo-600' : 'bg-gray-100 text-gray-400'}">${entry.register ? 'reg' : 'cons'}</span>
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
