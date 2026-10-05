<x-app-layout>

    <style>
        .tab-active { border-bottom: 3px solid #4f46e5; color: #4f46e5; }
    </style>

@php
    $today = now()->toDateString();
    $allRules = $companyDetails->rules;
    $vigentes = $allRules->filter(fn($r) => !$r->end_date || $r->end_date >= $today);
    $expiradas = $allRules->filter(fn($r) => $r->end_date && $r->end_date < $today);
    $vigenteIds = $vigentes->pluck('id')->values()->all();
    $expiradaIds = $expiradas->pluck('id')->values()->all();
    $todasIds = $allRules->pluck('id')->values()->all();
    $workerSearchStrings = $companyDetails->workers->map(fn($w) => strtolower($w->name . ' ' . $w->position))->values()->all();
@endphp

<div class="max-w-5xl mx-auto">

    <!-- Botão Voltar e Ações Rápidas -->
    <div class="my-8 flex items-center justify-between">
        <div class="flex items-center gap-4">
            <a href="{{ route('company.index') }}" class="p-2 bg-surface rounded-xl shadow-card text-ink-3 hover:text-grena-ink border border-line transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
            </a>
            <h1 class="text-3xl font-extrabold text-ink leading-tight">Perfil da Empresa</h1>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('company.edit', $companyDetails->id) }}" class="px-4 py-2 bg-surface border border-line text-ink rounded-lg font-bold text-sm shadow-card hover:bg-subtle transition">
                Editar Dados
            </a>
            <form action="{{ route('company.destroy', $companyDetails->id) }}" method="POST"
                  onsubmit="return confirm('Deseja realmente remover esta empresa?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="px-4 py-2 bg-danger-soft border border-danger/40 text-danger rounded-lg font-bold text-sm shadow-card hover:bg-danger-soft transition">
                    Excluir
                </button>
            </form>
        </div>
    </div>

    <!-- Cabeçalho / Hero Section -->
    <div class="bg-surface rounded-3xl shadow-pop border border-line overflow-hidden mb-8">
        <div class="h-32" style="{{ \App\View\AreaColor::style('externos') }}"></div>
        <div class="px-8 pb-8">
            <div class="relative flex justify-between items-end -mt-12 mb-6">
                {{-- Logo, ou as iniciais na cor de Externos quando não há (ou o arquivo sumiu). --}}
                @php
                    $initials = mb_strtoupper(collect(preg_split('/\s+/', trim($companyDetails->name), -1, PREG_SPLIT_NO_EMPTY))->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode(''));
                @endphp
                <div class="relative grid h-32 w-32 place-items-center overflow-hidden rounded-2xl border-4 border-surface shadow-pop"
                     style="{{ \App\View\AreaColor::style('externos') }}">
                    <span class="font-display text-3xl font-bold tracking-tight">{{ $initials }}</span>
                    @if ($companyDetails->image)
                        <img src="{{ asset('images/' . $companyDetails->image) }}" alt="Logo de {{ $companyDetails->name }}"
                             class="absolute inset-0 h-full w-full object-cover" onerror="this.remove()">
                    @endif
                </div>
                <div class="flex gap-3 mb-2">
                    <span class="px-3 py-1 bg-ok-soft text-ok rounded-full text-xs font-bold uppercase">Ativa</span>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="md:col-span-2">
                    <h2 class="text-4xl font-black text-ink mb-2">{{ $companyDetails->name }}</h2>
                    <p class="text-ink-2 font-medium leading-relaxed">{{ $companyDetails->description }}</p>
                </div>
                <div class="space-y-3 pt-4">
                    <div class="flex items-center text-sm text-ink-2">
                        <svg class="w-5 h-5 mr-2 text-grena-ink" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                        {{ $companyDetails->email }}
                    </div>
                    <div class="flex items-center text-sm text-ink-2">
                        <svg class="w-5 h-5 mr-2 text-grena-ink" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M3 5a2 2 0 012-2h3.28a1 1 0 00.948-.684l1.498-4.493a1 1 0 011.902 0l1.498 4.493a1 1 0 00.948.684H19a2 2 0 012 2v10a2 2 0 01-2 2h-3.28a1 1 0 00-.948.684l-1.498 4.493a1 1 0 01-1.902 0l-1.498-4.493A1 1 0 005.72 17H3a2 2 0 01-2-2V5z"></path></svg>
                        {{ $companyDetails->telephone }}
                    </div>
                    <div class="flex items-center text-sm text-ink-2">
                        <svg class="w-5 h-5 mr-2 text-grena-ink" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                        {{ $companyDetails->address }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Navegação por Abas -->
    <div class="bg-surface rounded-2xl shadow-pop border border-line overflow-hidden">
        <div class="flex border-b border-line bg-subtle">
            <button onclick="switchTab('workers')" id="tab-workers" class="tab-btn px-8 py-4 text-sm font-bold uppercase tracking-wider text-ink-2 hover:text-grena-ink transition tab-active">
                Funcionários
            </button>
            <button onclick="switchTab('rules')" id="tab-rules" class="tab-btn px-8 py-4 text-sm font-bold uppercase tracking-wider text-ink-2 hover:text-grena-ink transition">
                Regras de Acesso
            </button>
        </div>

        <!-- ==================== ABA: FUNCIONÁRIOS ==================== -->
        <div id="content-workers" class="tab-content p-6"
             x-data="{
                 search: '',
                 workerSearchStrings: {{ json_encode($workerSearchStrings) }},
                 get filteredCount() {
                     if (this.search === '') return this.workerSearchStrings.length;
                     const q = this.search.toLowerCase();
                     return this.workerSearchStrings.filter(s => s.includes(q)).length;
                 }
             }">

            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-extrabold text-ink">
                    Equipe Registrada
                    <span class="ml-1 text-sm font-normal text-ink-3">({{ $companyDetails->workers->count() }})</span>
                </h3>
                <a href="{{ route('company.worker.create', $companyDetails->id) }}" class="px-4 py-2 bg-grena text-white rounded-lg font-bold text-xs shadow-card hover:bg-grena-hover transition">
                    Adicionar Funcionário
                </a>
            </div>

            <!-- Campo de pesquisa -->
            <div class="relative mb-5">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-ink-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
                </svg>
                <input x-model="search" type="text" placeholder="Pesquisar por nome ou cargo..."
                    class="w-full pl-9 pr-4 py-2 text-sm border border-line rounded-xl bg-subtle text-ink placeholder-ink-3 focus:outline-none focus:ring-2 focus:ring-grena-tint transition">
                <button x-show="search !== ''" @click="search = ''"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-ink-3 hover:text-ink-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- Grid de funcionários -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @if($companyDetails->workers->isEmpty())
                    <p class="text-ink-2 italic col-span-2">Nenhum funcionário registado para esta empresa.</p>
                @endif

                @foreach($companyDetails->workers as $worker)
                <div class="flex items-center p-4 border border-line rounded-xl hover:bg-subtle transition shadow-card"
                     x-show="search === '' || workerSearchStrings[{{ $loop->index }}].includes(search.toLowerCase())">
                    @if($worker->image)
                        <img src="{{ asset('images/' . $worker->image) }}" alt="Foto de {{ $worker->name }}" class="h-12 w-12 rounded-full object-cover mr-4 shrink-0">
                    @else
                        <div class="h-12 w-12 rounded-full bg-grena-tint flex items-center justify-center text-grena-ink font-bold mr-4 shrink-0">
                            {{ strtoupper(substr($worker->name, 0, 1)) }}
                        </div>
                    @endif
                    <div class="flex-grow min-w-0">
                        <h4 class="font-bold text-ink truncate">{{ $worker->name }}</h4>
                        <p class="text-xs text-ink-2">{{ ucfirst($worker->position) }}</p>
                        @if($worker->latestAccessLog)
                            <p class="text-[11px] text-ink-3 mt-0.5 flex items-center gap-1">
                                <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                {{ $worker->latestAccessLog->created_at->diffForHumans() }}
                            </p>
                        @else
                            <p class="text-[11px] text-ink-3 mt-0.5">Sem acessos registados</p>
                        @endif
                    </div>
                    <div class="flex gap-2 items-center shrink-0">
                        <a href="{{ route('company.worker.show', [$companyDetails->id, $worker->id]) }}"
                           class="p-2 text-ink-3 hover:text-grena-ink transition" title="Ver Funcionário">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </a>
                        <form action="{{ route('company.worker.destroy', [$companyDetails->id, $worker->id]) }}" method="POST"
                              onsubmit="return confirm('Deseja realmente remover este funcionário?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="p-2 text-ink-3 hover:text-danger transition" title="Excluir Funcionário">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                            </button>
                        </form>
                    </div>
                </div>
                @endforeach
            </div>

            <!-- Sem resultados -->
            <p x-show="filteredCount === 0 && search !== ''"
               class="text-center text-ink-3 italic py-6">
                Nenhum funcionário encontrado para "<span x-text="search" class="font-semibold"></span>".
            </p>
        </div>

        <!-- ==================== ABA: REGRAS ==================== -->
        <div id="content-rules" class="tab-content p-6 hidden"
             x-data="{
                 ruleTab: 'vigentes',
                 selected: [],
                 vigenteIds: {{ json_encode($vigenteIds) }},
                 expiradaIds: {{ json_encode($expiradaIds) }},
                 todasIds: {{ json_encode($todasIds) }},
                 visibleIds() {
                     if (this.ruleTab === 'vigentes') return this.vigenteIds;
                     if (this.ruleTab === 'expiradas') return this.expiradaIds;
                     return this.todasIds;
                 },
                 allSelected() {
                     const vis = this.visibleIds();
                     return vis.length > 0 && vis.every(id => this.selected.includes(id));
                 },
                 someSelected() {
                     return this.selected.length > 0 && !this.allSelected();
                 },
                 toggleAll() {
                     const vis = this.visibleIds();
                     if (this.allSelected()) {
                         this.selected = this.selected.filter(id => !vis.includes(id));
                     } else {
                         vis.forEach(id => { if (!this.selected.includes(id)) this.selected.push(id); });
                     }
                 },
                 switchRuleTab(tab) { this.ruleTab = tab; this.selected = []; },
                 isVisible(vigente) {
                     return this.ruleTab === 'todas' ||
                            (this.ruleTab === 'vigentes' && vigente) ||
                            (this.ruleTab === 'expiradas' && !vigente);
                 }
             }">

            <!-- Cabeçalho -->
            <div class="flex justify-between items-center mb-5">
                <h3 class="text-xl font-extrabold text-ink">Regras de Acesso</h3>
                <a href="{{ route('company.rules.create', $companyDetails->id) }}" class="px-4 py-2 bg-grena text-white rounded-lg font-bold text-xs shadow-card hover:bg-grena-hover transition">
                    Nova Regra
                </a>
            </div>

            <!-- Sub-abas: Vigentes / Expiradas / Todas -->
            <div class="flex gap-1 p-1 bg-subtle rounded-xl mb-5">
                <button @click="switchRuleTab('vigentes')"
                    class="flex-1 flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg text-xs font-bold transition"
                    :class="ruleTab === 'vigentes' ? 'bg-surface text-grena-ink shadow-card' : 'text-ink-2 hover:text-ink'">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Vigentes
                    <span class="ml-0.5 px-1.5 py-0.5 rounded-full text-[10px]"
                          :class="ruleTab === 'vigentes' ? 'bg-grena-tint text-grena-ink' : 'bg-line text-ink-2'">
                        {{ $vigentes->count() }}
                    </span>
                </button>
                <button @click="switchRuleTab('expiradas')"
                    class="flex-1 flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg text-xs font-bold transition"
                    :class="ruleTab === 'expiradas' ? 'bg-surface text-danger shadow-card' : 'text-ink-2 hover:text-ink'">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Expiradas
                    <span class="ml-0.5 px-1.5 py-0.5 rounded-full text-[10px]"
                          :class="ruleTab === 'expiradas' ? 'bg-danger-soft text-danger' : 'bg-line text-ink-2'">
                        {{ $expiradas->count() }}
                    </span>
                </button>
                <button @click="switchRuleTab('todas')"
                    class="flex-1 flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg text-xs font-bold transition"
                    :class="ruleTab === 'todas' ? 'bg-surface text-ink shadow-card' : 'text-ink-2 hover:text-ink'">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                    Todas
                    <span class="ml-0.5 px-1.5 py-0.5 rounded-full text-[10px]"
                          :class="ruleTab === 'todas' ? 'bg-line text-ink' : 'bg-line text-ink-2'">
                        {{ $allRules->count() }}
                    </span>
                </button>
            </div>

            <!-- Barra de seleção / exclusão em lote -->
            <div class="flex items-center justify-between mb-4 min-h-[36px]">
                <label class="flex items-center gap-2 text-sm text-ink-2 cursor-pointer select-none">
                    <input type="checkbox"
                           @click="toggleAll()"
                           :checked="allSelected()"
                           :indeterminate="someSelected()"
                           class="w-4 h-4 rounded border-line-strong text-grena-ink focus:ring-grena-tint">
                    <span x-show="!allSelected()">Selecionar visíveis</span>
                    <span x-show="allSelected()">Desmarcar todas</span>
                    <span x-show="selected.length > 0" class="text-xs text-grena-ink font-semibold">
                        (<span x-text="selected.length"></span> selecionada(s))
                    </span>
                </label>

                <!-- Formulário de exclusão em lote -->
                <form x-show="selected.length > 0"
                      x-ref="bulkForm"
                      action="{{ route('company.rules.bulk-destroy', $companyDetails->id) }}"
                      method="POST">
                    @csrf
                    @method('DELETE')
                    <template x-for="id in selected" :key="id">
                        <input type="hidden" name="rule_ids[]" :value="id">
                    </template>
                    <button type="button"
                            @click="if (confirm('Remover ' + selected.length + ' regra(s) selecionada(s)? Esta ação não pode ser desfeita.')) $refs.bulkForm.submit()"
                            class="flex items-center gap-1.5 px-3 py-1.5 bg-danger hover:bg-grena-hover text-white rounded-lg text-xs font-bold transition shadow-card">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                        Excluir <span x-text="selected.length"></span> regra(s)
                    </button>
                </form>
            </div>

            <!-- Lista de regras -->
            @forelse($allRules as $rule)
                @php $isVigente = !$rule->end_date || $rule->end_date >= $today; @endphp
                <div class="mb-4"
                     x-show="isVisible({{ $isVigente ? 'true' : 'false' }})">
                    <div class="bg-surface border rounded-2xl shadow-card overflow-hidden border-line"
                         :class="selected.includes({{ $rule->id }}) ? 'ring-2 ring-grena-tint' : ''">
                        <div class="flex flex-col md:flex-row">
                            <!-- Checkbox + barra colorida -->
                            <div class="flex items-center gap-0 md:flex-col">
                                <div class="flex items-center justify-center w-10 md:w-10 md:pt-4 pl-3 md:pl-0">
                                    <input type="checkbox"
                                           x-model="selected"
                                           :value="{{ $rule->id }}"
                                           class="w-4 h-4 rounded border-line-strong text-grena-ink focus:ring-grena-tint cursor-pointer">
                                </div>
                                <div class="flex-1 md:flex-none w-full md:w-2 h-2 md:h-full {{ $rule['type'] === 'include' ? 'bg-grena' : 'bg-danger' }} md:rounded-none"></div>
                            </div>

                            <div class="px-5 py-3 flex-grow">
                                <div class="flex flex-wrap items-start justify-between gap-4">
                                    <div class="flex-grow">
                                        <div class="flex flex-wrap items-center gap-2 mb-1">
                                            <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase {{ $rule['type'] === 'include' ? 'bg-grena-tint text-grena-ink' : 'bg-danger-soft text-danger' }}">
                                                {{ $rule['type'] === 'include' ? 'Inclusão' : 'Exclusão' }}
                                            </span>
                                            @if(!$isVigente)
                                                <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase bg-subtle text-ink-2">
                                                    Expirada
                                                </span>
                                            @endif
                                            @if($rule->company_worker_id && $rule->worker)
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-grena-tint text-grena-ink">
                                                    Exclusiva: {{ $rule->worker->name }}
                                                </span>
                                            @endif
                                            <h4 class="font-extrabold text-ink text-base">{{ $rule['description'] }}</h4>
                                        </div>

                                        <!-- Vigência -->
                                        <div class="flex items-center text-xs text-ink-2 mb-3">
                                            <svg class="w-4 h-4 mr-1 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                            Vigência: <span class="ml-1 font-bold text-ink">{{ date('d/m/Y', strtotime($rule['start_date'])) }}</span>
                                            @if($rule['end_date'])
                                                <span class="mx-1">até</span>
                                                <span class="font-bold {{ !$isVigente ? 'text-danger' : 'text-ink' }}">
                                                    {{ date('d/m/Y', strtotime($rule['end_date'])) }}
                                                </span>
                                            @else
                                                <span class="ml-1 text-grena-ink font-bold">(Indeterminado)</span>
                                            @endif
                                        </div>

                                        <!-- Dias da Semana -->
                                        <div class="flex gap-1.5 mb-3">
                                            @foreach(['seg', 'ter', 'qua', 'qui', 'sex', 'sab', 'dom'] as $d)
                                                <span class="w-8 h-8 flex items-center justify-center rounded-lg text-[10px] font-black uppercase transition-colors {{ in_array($d, $rule['weekdays']->pluck('short_name_pt')->toArray()) ? 'bg-grena text-white shadow-card' : 'bg-subtle text-ink-3' }}">
                                                    {{ $d }}
                                                </span>
                                            @endforeach
                                        </div>

                                        @if($rule->start_time && $rule->end_time)
                                            <div class="flex items-center text-xs text-ink-2 mb-2">
                                                <svg class="w-4 h-4 mr-1 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                Horário: <span class="mx-1 font-bold text-ink">{{ date('H:i', strtotime($rule['start_time'])) }}</span> / <span class="font-bold text-ink">{{ date('H:i', strtotime($rule['end_time'])) }}</span>
                                            </div>
                                        @endif

                                        <p class="text-[10px] text-ink-3 mt-1">
                                            Criado por {{ $rule->creator?->name ?? '—' }}
                                            @if($rule->created_at) em {{ $rule->created_at->format('d/m/Y H:i') }} @endif
                                            @if($rule->editor && $rule->updated_at?->ne($rule->created_at))
                                                · Alterado por {{ $rule->editor->name }} em {{ $rule->updated_at->format('d/m/Y H:i') }}
                                            @endif
                                        </p>
                                    </div>

                                    <!-- Ações individuais -->
                                    <div class="flex items-start gap-1 shrink-0">
                                        <a href="{{ route('company.rules.edit', [$companyDetails->id, $rule->id]) }}"
                                           class="p-2 text-ink-3 hover:text-grena-ink transition" title="Editar Regra">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                        </a>
                                        <form action="{{ route('company.rules.destroy', [$companyDetails->id, $rule->id]) }}" method="POST"
                                              onsubmit="return confirm('Remover esta regra?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-2 text-ink-3 hover:text-danger transition" title="Excluir Regra">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                </svg>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <p class="text-ink-2 italic text-center py-8">Nenhuma regra de acesso cadastrada.</p>
            @endforelse

            <!-- Sem resultados na sub-aba -->
            <p x-show="visibleIds().length === 0" class="text-center text-ink-3 italic py-6">
                Nenhuma regra nesta categoria.
            </p>
        </div>
    </div>
</div>

<script>
    function switchTab(tabName) {
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.add('hidden');
        });
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.remove('tab-active');
        });
        document.getElementById('content-' + tabName).classList.remove('hidden');
        document.getElementById('tab-' + tabName).classList.add('tab-active');
    }
</script>
</x-app-layout>
