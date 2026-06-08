<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Parceiros Terceirizados
                </h2>
            </div>

            @include('partials.crud.search-create', [
                'buttonCreateText' => 'Nova Empresa',
                'routeCreate' => route('company.create'),
                'buttonSecondaryText' => 'Novo Funcionário',
                'routeSecondary' => route('company.worker.quick.create'),
            ])
        </div>
    </x-slot>

    <x-slot name="css">
    </x-slot>

    <div class="py-6">
        <br>
        <div class="mx-auto sm:px-6 lg:px-8 space-y-6 page-group">
            <div class="p-6 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg page" data-limit="5" data-actual="">

                <!-- Empresas -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="elements-container">
                    @foreach ($companies as $company)
                        @php
                            $imageUrl = str_contains($company->image ?? '', 'http')
                                ? $company->image
                                : ($company->image ? asset('images/' . $company->image) : '');
                            $fallbackImage = 'https://placehold.co/600x400/1E3A8A/ffffff?text=' . urlencode($company->name);
                            $today = now()->toDateString();
                            $hasRules = $company->rules->isNotEmpty();
                            $hasActiveRule = $company->rules->contains(function ($rule) use ($today) {
                                return $rule->type === 'include'
                                    && $rule->start_date <= $today
                                    && ($rule->end_date === null || $rule->end_date >= $today);
                            });
                            $accessAllowed = $accessStatuses[$company->id] ?? false;
                            $workersCount = $company->workers->count();
                            $rulesCount   = $company->rules->count();
                        @endphp

                        <div class="elements" data-company-id="{{ $company->id }}" data-details-url="{{ route('company.access-details', $company->id) }}">
                            <div class="bg-white dark:bg-gray-800 rounded-xl overflow-hidden shadow-2xl border border-gray-100 dark:border-gray-700 h-full flex flex-col transition hover:shadow-indigo-100 dark:hover:shadow-none">

                                <!-- Imagem + título: link para o perfil -->
                                <a href="{{ route('company.show', $company->id) }}" class="block group">
                                    <div class="h-36 overflow-hidden">
                                        <img class="w-full h-full object-cover group-hover:scale-105 transition duration-300"
                                            src="{{ $imageUrl }}"
                                            onerror="this.onerror=null;this.src='{{ $fallbackImage }}';"
                                            alt="Imagem de {{ $company->name }}"
                                            style="max-height: 200px">
                                    </div>

                                    <div class="px-4 pt-4 pb-1">
                                        <div class="flex items-start justify-between">
                                            <h3 class="text-xl font-extrabold text-gray-900 dark:text-white leading-tight group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition">
                                                {{ $company->name }}
                                            </h3>

                                            @if ($accessAllowed)
                                                <span class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-1 rounded-full bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300 whitespace-nowrap ml-2 flex-shrink-0">
                                                    <span class="w-2 h-2 rounded-full bg-green-500 inline-block animate-pulse"></span>
                                                    Acesso
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-1 rounded-full bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300 whitespace-nowrap ml-2 flex-shrink-0">
                                                    <span class="w-2 h-2 rounded-full bg-red-500 inline-block"></span>
                                                    Bloqueado
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </a>

                                <!-- Corpo: alertas + accordions -->
                                <div class="px-4 pb-4 pt-2 flex flex-col gap-2 flex-1">

                                    <!-- Alertas -->
                                    @if (!$hasRules)
                                        <div class="flex items-center gap-2 text-xs text-yellow-700 bg-yellow-50 dark:bg-yellow-900/30 dark:text-yellow-300 rounded-lg px-3 py-2">
                                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                            </svg>
                                            Nenhuma regra cadastrada
                                        </div>
                                    @elseif (!$hasActiveRule)
                                        <div class="flex items-center gap-2 text-xs text-orange-700 bg-orange-50 dark:bg-orange-900/30 dark:text-orange-300 rounded-lg px-3 py-2">
                                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            Nenhuma regra vigente
                                        </div>
                                    @endif

                                    <!-- Accordion: Funcionários -->
                                    <div class="border border-gray-100 dark:border-gray-700 rounded-lg overflow-hidden" data-accordion="workers">
                                        <button type="button" onclick="toggleAccordion(this, 'workers')"
                                                class="w-full flex items-center justify-between px-3 py-2.5 text-sm font-bold text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                                            <span class="inline-flex items-center gap-2">
                                                <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                </svg>
                                                {{ $workersCount }} {{ $workersCount === 1 ? 'Funcionário' : 'Funcionários' }}
                                            </span>
                                            <svg class="accordion-chevron w-4 h-4 text-gray-400 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                            </svg>
                                        </button>
                                        <div class="accordion-panel hidden px-3 pb-3">
                                            <div class="accordion-loading hidden py-3 flex justify-center">
                                                <div class="w-5 h-5 border-2 border-indigo-500 border-t-transparent rounded-full animate-spin"></div>
                                            </div>
                                            <ul class="accordion-list space-y-1"></ul>
                                            <a href="{{ route('company.worker.create', $company->id) }}"
                                               class="mt-2 w-full inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg text-xs font-bold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-900/30 hover:bg-indigo-100 dark:hover:bg-indigo-900/50 transition">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                                                </svg>
                                                Adicionar Funcionário
                                            </a>
                                        </div>
                                    </div>

                                    <!-- Accordion: Regras -->
                                    <div class="border border-gray-100 dark:border-gray-700 rounded-lg overflow-hidden" data-accordion="rules">
                                        <button type="button" onclick="toggleAccordion(this, 'rules')"
                                                class="w-full flex items-center justify-between px-3 py-2.5 text-sm font-bold text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                                            <span class="inline-flex items-center gap-2">
                                                <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                                </svg>
                                                {{ $rulesCount }} {{ $rulesCount === 1 ? 'Regra' : 'Regras' }}
                                            </span>
                                            <svg class="accordion-chevron w-4 h-4 text-gray-400 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                            </svg>
                                        </button>
                                        <div class="accordion-panel hidden px-3 pb-3">
                                            <div class="accordion-loading hidden py-3 flex justify-center">
                                                <div class="w-5 h-5 border-2 border-indigo-500 border-t-transparent rounded-full animate-spin"></div>
                                            </div>
                                            <ul class="accordion-list space-y-1.5"></ul>
                                            <a href="{{ route('company.rules.create', $company->id) }}"
                                               class="mt-2 w-full inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg text-xs font-bold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-900/30 hover:bg-indigo-100 dark:hover:bg-indigo-900/50 transition">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                                </svg>
                                                Nova Regra
                                            </a>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Resultados de funcionários (aparece ao digitar no campo de busca) -->
                <div id="worker-results-section" class="hidden mt-6 pt-6 border-t border-gray-100 dark:border-gray-700">
                    <p id="worker-section-title" class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-3">Funcionários encontrados</p>
                    <p id="worker-search-empty" class="text-sm text-gray-500 dark:text-gray-400 hidden">Nenhum funcionário encontrado.</p>
                    <div id="worker-search-results" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4" style="display:none;"></div>
                </div>

            </div>

            <div class="flex justify-center sm:px-6 lg:px-8 space-y-6 my-3">
                <div class="p-6 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg pagination">
                    @include('partials.navPagination')
                </div>
            </div>
        </div>
    </div>

    <x-slot name="js">
        <script src="{{ asset('js/information/filter.js') }}"></script>
        <script>
        (function () {
            const input      = document.getElementById('search-filter-text');
            const section    = document.getElementById('worker-results-section');
            const results    = document.getElementById('worker-search-results');
            const empty      = document.getElementById('worker-search-empty');
            const searchUrl  = '{{ route('company.worker.search') }}';
            let timer;

            function renderCard(w) {
                const initials = w.name.split(' ').filter(Boolean).slice(0, 2).map(n => n[0]).join('').toUpperCase();
                const avatar   = w.image
                    ? `<img src="${w.image}" alt="${w.name}" class="w-full h-full object-cover">`
                    : `<span class="text-xl font-bold text-indigo-600 dark:text-indigo-400">${initials}</span>`;

                const cpfHtml      = w.document  ? `<p class="text-xs text-gray-400 dark:text-gray-500 font-mono">${w.document}</p>` : '';
                const phoneHtml    = w.telephone ? `<p class="text-xs text-gray-400 dark:text-gray-500">${w.telephone}</p>` : '';
                const positionHtml = w.position  ? `<p class="text-xs text-gray-500 dark:text-gray-400">${w.position}</p>` : '';

                return `
                <a href="${w.worker_url}" class="block hover:scale-[1.02] transition duration-200">
                    <div class="bg-white dark:bg-gray-700 rounded-xl border border-gray-100 dark:border-gray-600 shadow p-4 flex items-center gap-4">
                        <div class="w-14 h-14 rounded-full bg-indigo-50 dark:bg-gray-600 flex items-center justify-center overflow-hidden flex-shrink-0">
                            ${avatar}
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="font-bold text-gray-900 dark:text-white truncate">${w.name}</p>
                            ${positionHtml}
                            ${cpfHtml}
                            ${phoneHtml}
                            <span role="link" tabindex="0"
                               class="mt-1 inline-flex items-center gap-1 text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline cursor-pointer"
                               onclick="event.preventDefault(); event.stopPropagation(); window.location.href='${w.company_url}';">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                </svg>
                                ${w.company_name}
                            </span>
                        </div>
                    </div>
                </a>`;
            }

            // Mantém visíveis as empresas que possuem funcionários correspondentes à busca,
            // mesmo quando o nome da empresa em si não casa com o termo pesquisado.
            function revealCompaniesWithWorkers(workers) {
                const companyIds = new Set(workers.map(w => String(w.company_id)));
                document.querySelectorAll('#elements-container .elements').forEach(card => {
                    if (companyIds.has(card.getAttribute('data-company-id'))) {
                        card.style.display = '';
                    }
                });
            }

            function showWorkers(workers, emptyMsg) {
                results.innerHTML = '';
                if (workers.length === 0) {
                    empty.textContent = emptyMsg;
                    empty.classList.remove('hidden');
                    results.style.display = 'none';
                } else {
                    empty.classList.add('hidden');
                    workers.forEach(w => results.insertAdjacentHTML('beforeend', renderCard(w).trim()));
                    results.style.display = 'grid';
                }
                section.classList.remove('hidden');
                revealCompaniesWithWorkers(workers);
            }

            async function doSearch(q) {
                if (q.length < 2) {
                    section.classList.add('hidden');
                    return;
                }
                const res = await fetch(searchUrl + '?q=' + encodeURIComponent(q));
                const workers = await res.json();
                showWorkers(workers, 'Nenhum funcionário encontrado.');
            }

            if (input) {
                input.addEventListener('input', function () {
                    clearTimeout(timer);
                    const q = this.value.trim();
                    if (q.length < 2) {
                        section.classList.add('hidden');
                        return;
                    }
                    timer = setTimeout(() => doSearch(q), 300);
                });
            }
        })();
        </script>

        <script>
        // ---- Accordions do card de empresa (carregamento sob demanda) ----
        // Os detalhes (funcionários + status de acesso e regras) só são buscados
        // quando o usuário abre um accordion, e ficam em cache por empresa.
        (function () {
            const detailsCache = {};

            function escapeHtml(str) {
                return String(str ?? '').replace(/[&<>"']/g, s => ({
                    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
                })[s]);
            }

            function fetchDetails(url) {
                if (!detailsCache[url]) {
                    detailsCache[url] = fetch(url)
                        .then(r => {
                            if (!r.ok) throw new Error('HTTP ' + r.status);
                            return r.json();
                        })
                        .catch(err => { delete detailsCache[url]; throw err; });
                }
                return detailsCache[url];
            }

            function renderWorkers(list, workers) {
                if (!workers.length) {
                    list.innerHTML = '<li class="text-xs italic text-gray-400 dark:text-gray-500 py-1">Nenhum funcionário cadastrado.</li>';
                    return;
                }
                // Orderna por nome e exibe um badge indicando se o acesso está permitido ou bloqueado
                workers.sort((a, b) => a.name.localeCompare(b.name));

                list.innerHTML = workers.map(w => {
                    const badge = w.allowed
                        ? '<span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300 whitespace-nowrap"><span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>Acesso</span>'
                        : '<span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300 whitespace-nowrap"><span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>Bloqueado</span>';
                    return `<li class="flex items-center justify-between gap-2 py-1 border-b border-gray-50 dark:border-gray-700/50 last:border-0">
                        <a href="${w.url}" class="text-sm text-gray-700 dark:text-gray-300 hover:text-indigo-600 dark:hover:text-indigo-400 truncate">${escapeHtml(w.name)}</a>
                        ${badge}
                    </li>`;
                }).join('');
            }

            function renderRules(list, rules) {
                if (!rules.length) {
                    list.innerHTML = '<li class="text-xs italic text-gray-400 dark:text-gray-500 py-1">Nenhuma regra cadastrada.</li>';
                    return;
                }
                list.innerHTML = rules.map(r => {
                    const isInclude = r.type === 'include';
                    const typeBadge = isInclude
                        ? '<span class="text-[10px] font-black uppercase px-1.5 py-0.5 rounded bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-400">Inclusão</span>'
                        : '<span class="text-[10px] font-black uppercase px-1.5 py-0.5 rounded bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400">Exclusão</span>';
                    const workerBadge = r.worker
                        ? `<span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-400">${escapeHtml(r.worker)}</span>`
                        : '';
                    const period = r.start_date
                        ? `${r.start_date}${r.end_date ? ' até ' + r.end_date : ' (indeterminado)'}`
                        : '';
                    const time = (r.start_time && r.end_time) ? ` · ${r.start_time}–${r.end_time}` : '';
                    const days = (r.weekdays && r.weekdays.length) ? ` · ${r.weekdays.join(', ')}` : '';
                    return `<li class="py-1 border-b border-gray-50 dark:border-gray-700/50 last:border-0">
                        <div class="flex items-center gap-1.5 flex-wrap">
                            ${typeBadge}${workerBadge}
                            <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">${escapeHtml(r.description || 'Regra')}</span>
                        </div>
                        <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-0.5">${escapeHtml(period + time + days)}</p>
                    </li>`;
                }).join('');
            }

            window.toggleAccordion = function (btn, type) {
                const wrapper = btn.closest('[data-accordion]');
                const panel   = wrapper.querySelector('.accordion-panel');
                const chevron = btn.querySelector('.accordion-chevron');
                const willOpen = panel.classList.contains('hidden');

                panel.classList.toggle('hidden', !willOpen);
                chevron.classList.toggle('rotate-180', willOpen);

                if (!willOpen || wrapper.dataset.loaded === 'true' || wrapper.dataset.loading === 'true') {
                    return;
                }

                const card    = btn.closest('.elements');
                const loading = wrapper.querySelector('.accordion-loading');
                const list    = wrapper.querySelector('.accordion-list');

                wrapper.dataset.loading = 'true';
                loading.classList.remove('hidden');

                fetchDetails(card.dataset.detailsUrl)
                    .then(data => {
                        if (type === 'workers') renderWorkers(list, data.workers || []);
                        else renderRules(list, data.rules || []);
                        wrapper.dataset.loaded = 'true';
                    })
                    .catch(() => {
                        list.innerHTML = '<li class="text-xs text-red-500 py-1">Erro ao carregar. Tente novamente.</li>';
                    })
                    .finally(() => {
                        loading.classList.add('hidden');
                        delete wrapper.dataset.loading;
                    });
            };
        })();
        </script>
    </x-slot>
</x-app-layout>
