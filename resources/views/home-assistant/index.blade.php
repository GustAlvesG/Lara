<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Home Assistant
        </h2>
    </x-slot>

    <x-slot name="css">
        <style>
            [x-cloak]{ display: none !important; }
            /* Trechos "forçado desligado" da linha do tempo */
            .ha-hatch { background-image: repeating-linear-gradient(135deg, rgba(255,255,255,.45) 0 3px, transparent 3px 6px); }
        </style>
    </x-slot>

    @php
        $onCount       = $states->filter(fn ($s) => $s->on)->count();
        $manualCount   = $states->filter(fn ($s) => $s->isManual())->count();
        $openModal     = old('_modal');
        $initialTab    = $openModal && str_contains($openModal, 'schedule') ? 'schedules' : 'contactors';
    @endphp

    <div class="py-6"
        x-data="{
            tab: ['contactors', 'schedules', 'self-service'].includes(location.hash.slice(1)) ? location.hash.slice(1) : '{{ $initialTab }}',
            help: false,
            go(tab) { this.tab = tab; history.replaceState(null, '', '#' + tab); },
        }">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- Flash messages --}}
            @if(session('success'))
                <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)" x-transition
                    class="flex items-center gap-3 p-4 bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 text-green-800 dark:text-green-300 rounded-xl text-sm">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span class="flex-1">{{ session('success') }}</span>
                    <button type="button" @click="show = false" class="text-green-600/70 hover:text-green-800 dark:hover:text-green-200" aria-label="Fechar">&times;</button>
                </div>
            @endif
            @if(session('error'))
                <div class="p-4 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 text-red-800 dark:text-red-300 rounded-xl text-sm">
                    {{ session('error') }}
                </div>
            @endif
            {{-- Erros de formulário aparecem dentro do modal que os gerou; este é o reserva --}}
            @if($errors->any() && ! $openModal)
                <div class="p-4 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 text-red-800 dark:text-red-300 rounded-xl text-sm">
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif

            {{-- ══════════════ Resumo ══════════════ --}}
            <section class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="p-5 sm:p-6 flex flex-wrap items-start justify-between gap-4">
                    <div class="flex items-center gap-4 min-w-0">
                        <div class="w-12 h-12 rounded-2xl bg-amber-100 dark:bg-amber-900/40 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                        </div>
                        <div class="min-w-0">
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white">Iluminação automática</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                Estado de {{ $now->format('d/m') }} às {{ $now->format('H:i') }}
                                <span class="text-gray-300 dark:text-gray-600">·</span>
                                <a href="{{ route('home-assistant.index') }}" @click.prevent="location.reload()" class="font-medium text-red-800 dark:text-red-400 hover:underline">Atualizar</a>
                            </p>
                        </div>
                    </div>

                    <button type="button" @click="help = !help"
                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                        :aria-expanded="help">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Como funciona
                        <svg class="w-3.5 h-3.5 transition-transform" :class="help && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                </div>

                {{-- Números --}}
                <dl class="grid grid-cols-2 lg:grid-cols-4 border-t border-gray-100 dark:border-gray-700 divide-x divide-y lg:divide-y-0 divide-gray-100 dark:divide-gray-700">
                    <div class="p-4 sm:px-6">
                        <dt class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Ligados agora</dt>
                        <dd class="mt-1 text-2xl font-extrabold text-amber-500">{{ $onCount }}<span class="text-sm font-semibold text-gray-400 dark:text-gray-500"> / {{ $contactors->count() }}</span></dd>
                    </div>
                    <div class="p-4 sm:px-6">
                        <dt class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Em modo manual</dt>
                        <dd class="mt-1 text-2xl font-extrabold {{ $manualCount ? 'text-violet-600 dark:text-violet-400' : 'text-gray-800 dark:text-gray-200' }}">{{ $manualCount }}</dd>
                    </div>
                    <div class="p-4 sm:px-6">
                        <dt class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Agendamentos ativos</dt>
                        <dd class="mt-1 text-2xl font-extrabold text-gray-800 dark:text-gray-200">{{ $activeOverrides->count() }}</dd>
                    </div>
                    <div class="p-4 sm:px-6">
                        <dt class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Valendo agora</dt>
                        <dd class="mt-1 text-2xl font-extrabold text-sky-600 dark:text-sky-400">{{ count($inEffectIds) }}</dd>
                    </div>
                </dl>

                {{-- Como funciona --}}
                <div x-show="help" x-cloak x-transition class="border-t border-gray-100 dark:border-gray-700 bg-gray-50/70 dark:bg-gray-900/30 p-5 sm:p-6">
                    <p class="text-sm text-gray-600 dark:text-gray-300 mb-4">
                        O Home Assistant pergunta ao sistema, a cada poucos minutos, se cada contator deve estar ligado.
                        A resposta segue esta ordem. A primeira regra que se aplica decide:
                    </p>
                    <ol class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <li class="flex gap-3 p-4 rounded-xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700">
                            <span class="w-7 h-7 rounded-full bg-violet-100 dark:bg-violet-900/40 text-violet-700 dark:text-violet-300 text-sm font-bold flex items-center justify-center shrink-0">1</span>
                            <div>
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">Controle manual</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">“Ligado” ou “Desligado” no cartão do contator. Vale até o fim do dia ou até voltar para “Automático”.</p>
                            </div>
                        </li>
                        <li class="flex gap-3 p-4 rounded-xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700">
                            <span class="w-7 h-7 rounded-full bg-sky-100 dark:bg-sky-900/40 text-sky-700 dark:text-sky-300 text-sm font-bold flex items-center justify-center shrink-0">2</span>
                            <div>
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">Agendamentos</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Regras por dia e horário. Se duas valem ao mesmo tempo, vence a de maior prioridade. Fora das faixas de horário, passam a valer as reservas.</p>
                            </div>
                        </li>
                        <li class="flex gap-3 p-4 rounded-xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700">
                            <span class="w-7 h-7 rounded-full bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300 text-sm font-bold flex items-center justify-center shrink-0">3</span>
                            <div>
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">Reservas</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Liga {{ \App\Services\HomeAssistant\ContactorStateResolver::MARGIN_MINUTES }} min antes e desliga {{ \App\Services\HomeAssistant\ContactorStateResolver::MARGIN_MINUTES }} min depois de cada reserva confirmada nos espaços do contator. Sem reserva, fica desligado.</p>
                            </div>
                        </li>
                    </ol>
                </div>
            </section>

            {{-- ══════════════ Abas ══════════════ --}}
            <div class="flex flex-wrap items-center justify-between gap-3">
                <nav class="inline-flex p-1 rounded-xl bg-gray-200/70 dark:bg-gray-800 border border-transparent dark:border-gray-700" role="tablist">
                    <button type="button" role="tab" @click="go('contactors')" :aria-selected="tab === 'contactors'"
                        :class="tab === 'contactors' ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200'"
                        class="px-4 py-2 rounded-lg text-sm font-semibold transition">
                        Contatores
                        <span class="ml-1 text-xs font-bold text-gray-400">{{ $contactors->count() }}</span>
                    </button>
                    <button type="button" role="tab" @click="go('schedules')" :aria-selected="tab === 'schedules'"
                        :class="tab === 'schedules' ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200'"
                        class="px-4 py-2 rounded-lg text-sm font-semibold transition">
                        Agendamentos
                        <span class="ml-1 text-xs font-bold text-gray-400">{{ $activeOverrides->count() }}</span>
                    </button>
                    <button type="button" role="tab" @click="go('self-service')" :aria-selected="tab === 'self-service'"
                        :class="tab === 'self-service' ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200'"
                        class="px-4 py-2 rounded-lg text-sm font-semibold transition">
                        Autoatendimento
                        <span class="ml-1 text-xs font-bold text-gray-400">{{ $selfServicePlaces->count() }}</span>
                    </button>
                </nav>

                <div class="flex items-center gap-2">
                    <button type="button" x-show="tab === 'contactors'" @click="$dispatch('open-modal', 'contactor-new')"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-red-800 hover:bg-red-700 text-white text-sm font-semibold rounded-lg shadow-sm transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Novo contator
                    </button>
                    <button type="button" x-show="tab === 'schedules'" x-cloak @click="$dispatch('open-modal', 'schedule-new')"
                        @disabled($contactors->isEmpty())
                        title="{{ $contactors->isEmpty() ? 'Cadastre um contator antes' : '' }}"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-red-800 hover:bg-red-700 text-white text-sm font-semibold rounded-lg shadow-sm transition disabled:opacity-50 disabled:cursor-not-allowed">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Novo agendamento
                    </button>
                </div>
            </div>

            {{-- ══════════════ Contatores ══════════════ --}}
            <section x-show="tab === 'contactors'" role="tabpanel" class="space-y-4">
                @if($contactors->isEmpty())
                    <div class="p-10 bg-white dark:bg-gray-800 rounded-2xl border border-dashed border-gray-300 dark:border-gray-600 text-center">
                        <div class="w-12 h-12 mx-auto rounded-2xl bg-gray-100 dark:bg-gray-700 text-gray-400 flex items-center justify-center">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        </div>
                        <h4 class="mt-4 font-semibold text-gray-900 dark:text-white">Nenhum contator cadastrado</h4>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400 max-w-md mx-auto">
                            Um contator é o interruptor do Home Assistant (ex.: <code class="font-mono text-xs">switch.quadra_1</code>).
                            Cadastre-o aqui e vincule-o aos espaços na tela de cada espaço.
                        </p>
                        <button type="button" @click="$dispatch('open-modal', 'contactor-new')"
                            class="mt-5 inline-flex items-center gap-2 px-4 py-2 bg-red-800 hover:bg-red-700 text-white text-sm font-semibold rounded-lg transition">
                            Cadastrar o primeiro contator
                        </button>
                    </div>
                @else
                    {{-- Legenda da linha do tempo --}}
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                        <span class="font-semibold text-gray-600 dark:text-gray-300">Linha do tempo de hoje:</span>
                        <span class="inline-flex items-center gap-1.5"><span class="w-3 h-2 rounded-sm bg-amber-400"></span>Reserva</span>
                        <span class="inline-flex items-center gap-1.5"><span class="w-3 h-2 rounded-sm bg-sky-500"></span>Agendamento</span>
                        <span class="inline-flex items-center gap-1.5"><span class="w-3 h-2 rounded-sm bg-violet-500"></span>Manual</span>
                        <span class="inline-flex items-center gap-1.5"><span class="w-3 h-2 rounded-sm bg-gray-400 dark:bg-gray-500 ha-hatch"></span>Forçado desligado</span>
                        <span class="inline-flex items-center gap-1.5"><span class="w-0.5 h-3 bg-red-600"></span>Agora</span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                        @foreach($contactors as $contactor)
                            @include('home-assistant.partials.contactor-card', [
                                'contactor' => $contactor,
                                'state'     => $states[$contactor->id],
                                'timeline'  => $timelines[$contactor->id],
                            ])
                        @endforeach
                    </div>
                @endif
            </section>

            {{-- ══════════════ Agendamentos ══════════════ --}}
            <section x-show="tab === 'schedules'" x-cloak role="tabpanel" class="space-y-4" x-data="{ archived: false }">
                @if($activeOverrides->isEmpty() && $archivedOverrides->isEmpty())
                    <div class="p-10 bg-white dark:bg-gray-800 rounded-2xl border border-dashed border-gray-300 dark:border-gray-600 text-center">
                        <div class="w-12 h-12 mx-auto rounded-2xl bg-gray-100 dark:bg-gray-700 text-gray-400 flex items-center justify-center">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        </div>
                        <h4 class="mt-4 font-semibold text-gray-900 dark:text-white">Nenhum agendamento</h4>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400 max-w-md mx-auto">
                            Sem agendamentos, as luzes seguem só as reservas. Crie um para, por exemplo, acender a quadra
                            toda noite das 18:00 às 22:00 ou manter um espaço desligado durante uma manutenção.
                        </p>
                        @if($contactors->isNotEmpty())
                            <button type="button" @click="$dispatch('open-modal', 'schedule-new')"
                                class="mt-5 inline-flex items-center gap-2 px-4 py-2 bg-red-800 hover:bg-red-700 text-white text-sm font-semibold rounded-lg transition">
                                Criar agendamento
                            </button>
                        @endif
                    </div>
                @else
                    @if($archivedOverrides->isNotEmpty())
                        <div class="flex items-center gap-2 text-sm">
                            <button type="button" @click="archived = false"
                                :class="!archived ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-200/70 dark:hover:bg-gray-700'"
                                class="px-3 py-1.5 rounded-full font-medium transition">
                                Ativos · {{ $activeOverrides->count() }}
                            </button>
                            <button type="button" @click="archived = true"
                                :class="archived ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-200/70 dark:hover:bg-gray-700'"
                                class="px-3 py-1.5 rounded-full font-medium transition">
                                Pausados e expirados · {{ $archivedOverrides->count() }}
                            </button>
                        </div>
                    @endif

                    <div x-show="!archived" class="space-y-3">
                        @forelse($activeOverrides as $override)
                            @include('home-assistant.partials.schedule-card', ['override' => $override, 'inEffect' => in_array($override->id, $inEffectIds)])
                        @empty
                            <div class="p-6 bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 text-center text-sm text-gray-500 dark:text-gray-400">
                                Nenhum agendamento ativo. As luzes seguem só as reservas.
                            </div>
                        @endforelse
                    </div>

                    @if($archivedOverrides->isNotEmpty())
                        <div x-show="archived" x-cloak class="space-y-3">
                            @foreach($archivedOverrides as $override)
                                @include('home-assistant.partials.schedule-card', ['override' => $override, 'inEffect' => false])
                            @endforeach
                        </div>
                    @endif
                @endif
            </section>

            {{-- ══════════════ Autoatendimento do sócio ══════════════ --}}
            <section x-show="tab === 'self-service'" x-cloak role="tabpanel" class="space-y-4">
                @include('home-assistant.partials.self-service')
            </section>
        </div>

        {{-- ══════════════ Modais ══════════════ --}}
        @include('home-assistant.partials.contactor-form', ['contactor' => null])
        @foreach($contactors as $contactor)
            @include('home-assistant.partials.contactor-form', ['contactor' => $contactor])
        @endforeach

        @include('home-assistant.partials.schedule-form', ['override' => null])
        @foreach($activeOverrides->concat($archivedOverrides) as $override)
            @include('home-assistant.partials.schedule-form', ['override' => $override])
        @endforeach
    </div>

    <x-slot name="js">
        <script>
            // Formulário de agendamento: estado dos campos e resumo em linguagem natural.
            function scheduleForm(config) {
                const DAY_NAMES = { 1: 'dom', 2: 'seg', 3: 'ter', 4: 'qua', 5: 'qui', 6: 'sex', 7: 'sáb' };
                const newWindow = () => ({ turn_on_at: '18:00', turn_off_at: '22:00', state: 'on' });

                return {
                    mode: config.mode || 'schedule_override',
                    windows: (config.windows && config.windows.length) ? config.windows : [newWindow()],
                    weekdays: (config.weekdays || []).map(String),
                    contactors: (config.contactors || []).map(String),
                    contactorNames: config.contactorNames || {},
                    startDate: config.startDate || '',
                    endDate: config.endDate || '',
                    advanced: config.advanced || false,

                    addWindow() { this.windows.push(newWindow()); },
                    removeWindow(i) { this.windows.splice(i, 1); },

                    setDays(ids) { this.weekdays = ids.map(String); },
                    daysAre(ids) {
                        return ids.length === this.weekdays.length && ids.every(id => this.weekdays.includes(String(id)));
                    },

                    allContactors() { return Object.keys(this.contactorNames); },
                    toggleAllContactors() {
                        this.contactors = this.contactors.length === this.allContactors().length ? [] : this.allContactors();
                    },

                    crossesMidnight(w) { return w.turn_on_at && w.turn_off_at && w.turn_off_at < w.turn_on_at; },

                    get summary() {
                        const who = this.contactors.length === 0
                            ? null
                            : this.contactors.length <= 2
                                ? this.contactors.map(id => this.contactorNames[id]).join(' e ')
                                : this.contactors.length + ' contatores';

                        let days = 'todos os dias';
                        if (this.weekdays.length && this.weekdays.length < 7) {
                            if (this.daysAre([2, 3, 4, 5, 6])) days = 'de segunda a sexta';
                            else if (this.daysAre([1, 7])) days = 'nos fins de semana';
                            else days = 'em ' + [...this.weekdays].sort().map(id => DAY_NAMES[id]).join(', ');
                        }

                        let what;
                        if (this.mode === 'manual_on') what = 'Mantém ligado o dia todo';
                        else if (this.mode === 'manual_off') what = 'Mantém desligado o dia todo';
                        else what = this.windows
                            .map((w, i) => (i === 0 ? (w.state === 'on' ? 'Liga' : 'Desliga') : (w.state === 'on' ? 'liga' : 'desliga'))
                                + ' das ' + (w.turn_on_at || '--:--') + ' às ' + (w.turn_off_at || '--:--'))
                            .join(', ');

                        const fmt = d => d.split('-').reverse().join('/');
                        let period = '';
                        if (this.startDate && this.endDate) period = ', de ' + fmt(this.startDate) + ' a ' + fmt(this.endDate);
                        else if (this.startDate) period = ', a partir de ' + fmt(this.startDate);
                        else if (this.endDate) period = ', até ' + fmt(this.endDate);

                        return {
                            ok: !!who,
                            text: what + (who ? ' · ' + who : '') + ' · ' + days + period + '.',
                        };
                    },
                };
            }

            // Painel de controle: recarrega a cada minuto para o estado não envelhecer na tela,
            // mas nunca com um modal aberto ou com alguém digitando.
            setInterval(function () {
                const typing = document.activeElement && ['INPUT', 'SELECT', 'TEXTAREA'].includes(document.activeElement.tagName);
                const modalOpen = document.body.classList.contains('overflow-y-hidden');
                if (document.visibilityState === 'visible' && !typing && !modalOpen) {
                    location.reload();
                }
            }, 60000);
        </script>
    </x-slot>
</x-app-layout>
