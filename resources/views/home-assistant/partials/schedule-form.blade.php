{{--
    Modal de criar/editar agendamento.
    Parâmetros:
      $override    ?HomeAssistantOverride  null ao criar
      $contactors  Collection  (do escopo da página)
      $weekdays    Collection  (do escopo da página)
--}}
@php
    $modalId = $override ? 'schedule-' . $override->id : 'schedule-new';
    // Depois de um erro de validação, reabre este modal com o que foi digitado
    $useOld  = old('_modal') === $modalId;

    $windows = $useOld
        ? array_values(old('windows', []))
        : ($override
            ? $override->windows->map(fn ($w) => [
                'turn_on_at'  => substr($w->turn_on_at, 0, 5),
                'turn_off_at' => substr($w->turn_off_at, 0, 5),
                'state'       => $w->state ?? 'on',
            ])->values()->all()
            : []);

    $config = [
        'mode'           => $useOld ? old('mode') : ($override->mode ?? 'schedule_override'),
        'windows'        => $windows,
        'weekdays'       => $useOld ? old('weekdays', []) : ($override ? $override->weekdays->pluck('id')->all() : []),
        'contactors'     => $useOld ? old('contactors', []) : ($override ? $override->contactors->pluck('id')->all() : []),
        'contactorNames' => $contactors->mapWithKeys(fn ($c) => [(string) $c->id => $c->name]),
        'startDate'      => $useOld ? old('start_date') : optional($override?->start_date)->format('Y-m-d'),
        'endDate'        => $useOld ? old('end_date') : optional($override?->end_date)->format('Y-m-d'),
    ];

    $name     = $useOld ? old('name') : ($override->name ?? '');
    $priority = $useOld ? old('priority') : ($override->priority ?? 0);
    $isActive = $useOld ? (bool) old('is_active') : (! $override || $override->is_active);
    $config['advanced'] = (bool) ($config['startDate'] || $config['endDate'] || $priority > 0 || ! $isActive);

    $modes = [
        'schedule_override' => ['Por horário', 'Liga ou desliga em faixas de horário. Fora delas, valem as reservas.', 'sky',
            'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
        'manual_on'         => ['Manter ligado', 'O dia inteiro, nos dias escolhidos. Ex.: evento.', 'amber',
            'M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z'],
        'manual_off'        => ['Manter desligado', 'O dia inteiro, mesmo com reserva. Ex.: manutenção.', 'gray',
            'M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636'],
    ];

    $modeActive = [
        'sky'   => 'border-sky-500 bg-sky-50 dark:bg-sky-900/30 ring-1 ring-sky-500',
        'amber' => 'border-amber-500 bg-amber-50 dark:bg-amber-900/30 ring-1 ring-amber-500',
        'gray'  => 'border-gray-600 bg-gray-100 dark:bg-gray-700/60 ring-1 ring-gray-600',
    ];
    $modeIcon = [
        'sky'   => 'bg-sky-100 text-sky-600 dark:bg-sky-900/50 dark:text-sky-300',
        'amber' => 'bg-amber-100 text-amber-600 dark:bg-amber-900/50 dark:text-amber-300',
        'gray'  => 'bg-gray-200 text-gray-600 dark:bg-gray-600 dark:text-gray-200',
    ];

    $input = 'w-full border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-gray-900 text-gray-900 dark:text-white focus:ring-red-800 focus:border-red-800';
@endphp

<x-modal :name="$modalId" :show="$useOld && $errors->any()" maxWidth="2xl">
    <form method="POST" action="{{ $override ? route('home-assistant.overrides.update', $override) : route('home-assistant.overrides.store') }}"
        x-data="scheduleForm(@js($config))">
        @csrf
        @if($override) @method('PUT') @endif
        <input type="hidden" name="_modal" value="{{ $modalId }}">

        {{-- Cabeçalho --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-700">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ $override ? 'Editar agendamento' : 'Novo agendamento' }}</h3>
            <button type="button" @click="$dispatch('close-modal', '{{ $modalId }}')" class="p-1 rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-200" aria-label="Fechar">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <div class="px-6 py-5 space-y-6 max-h-[70vh] overflow-y-auto">
            @if($useOld && $errors->any())
                <div class="p-3 rounded-lg bg-red-50 dark:bg-red-900/30 text-sm text-red-700 dark:text-red-300 space-y-0.5">
                    @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                </div>
            @endif

            {{-- Nome --}}
            <div>
                <label for="{{ $modalId }}-name" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Nome</label>
                <input id="{{ $modalId }}-name" type="text" name="name" required maxlength="255" value="{{ $name }}"
                    placeholder="Ex.: Refletores à noite" class="{{ $input }}">
            </div>

            {{-- 1. O que fazer --}}
            <fieldset>
                <legend class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                    <span class="w-5 h-5 rounded-full bg-gray-900 dark:bg-white text-white dark:text-gray-900 text-[11px] font-bold flex items-center justify-center">1</span>
                    O que fazer
                </legend>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                    @foreach($modes as $value => [$label, $description, $color, $icon])
                        <label class="relative flex sm:flex-col gap-3 sm:gap-2 p-3 rounded-xl border cursor-pointer transition"
                            :class="mode === '{{ $value }}' ? '{{ $modeActive[$color] }}' : 'border-gray-200 dark:border-gray-600 hover:border-gray-300 dark:hover:border-gray-500'">
                            <input type="radio" name="mode" value="{{ $value }}" x-model="mode" class="sr-only">
                            <span class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 {{ $modeIcon[$color] }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $icon }}"/></svg>
                            </span>
                            <span>
                                <span class="block text-sm font-semibold text-gray-900 dark:text-white">{{ $label }}</span>
                                <span class="block text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $description }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            {{-- 1b. Faixas de horário --}}
            <div x-show="mode === 'schedule_override'" x-cloak class="space-y-2 -mt-2 p-4 rounded-xl bg-sky-50/60 dark:bg-sky-900/10 border border-sky-100 dark:border-sky-900/50">
                <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">Faixas de horário</p>

                <template x-for="(w, i) in windows" :key="i">
                    <div class="flex flex-wrap sm:flex-nowrap items-center gap-2">
                        <input type="hidden" :name="`windows[${i}][state]`" :value="w.state" :disabled="mode !== 'schedule_override'">
                        <div class="flex rounded-lg p-0.5 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-600 text-xs font-semibold shrink-0">
                            <button type="button" @click="w.state = 'on'"
                                :class="w.state === 'on' ? 'bg-amber-400 text-white' : 'text-gray-500 dark:text-gray-400'"
                                class="px-3 py-1.5 rounded-md transition">Liga</button>
                            <button type="button" @click="w.state = 'off'"
                                :class="w.state === 'off' ? 'bg-gray-700 text-white' : 'text-gray-500 dark:text-gray-400'"
                                class="px-3 py-1.5 rounded-md transition">Desliga</button>
                        </div>
                        <span class="text-sm text-gray-500 dark:text-gray-400">das</span>
                        <input type="time" x-model="w.turn_on_at" :name="`windows[${i}][turn_on_at]`" required
                            :disabled="mode !== 'schedule_override'"
                            class="w-28 border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-gray-900 text-gray-900 dark:text-white focus:ring-red-800 focus:border-red-800">
                        <span class="text-sm text-gray-500 dark:text-gray-400">às</span>
                        <input type="time" x-model="w.turn_off_at" :name="`windows[${i}][turn_off_at]`" required
                            :disabled="mode !== 'schedule_override'"
                            class="w-28 border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-gray-900 text-gray-900 dark:text-white focus:ring-red-800 focus:border-red-800">
                        <span x-show="crossesMidnight(w)" class="text-[11px] font-medium text-sky-700 dark:text-sky-300 whitespace-nowrap">+1 dia</span>
                        <button type="button" @click="removeWindow(i)" x-show="windows.length > 1" aria-label="Remover faixa"
                            class="ml-auto p-1.5 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </template>

                <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
                    <button type="button" @click="addWindow()"
                        class="inline-flex items-center gap-1 text-sm font-semibold text-sky-700 dark:text-sky-300 hover:underline">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Adicionar faixa
                    </button>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Faixas podem passar da meia-noite (22:00 às 02:00).</p>
                </div>
            </div>

            {{-- 2. Quando --}}
            <fieldset>
                <legend class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                    <span class="w-5 h-5 rounded-full bg-gray-900 dark:bg-white text-white dark:text-gray-900 text-[11px] font-bold flex items-center justify-center">2</span>
                    Em quais dias
                </legend>
                <div class="flex flex-wrap gap-2 mb-2 text-xs font-semibold">
                    <button type="button" @click="setDays([])"
                        :class="weekdays.length === 0 || weekdays.length === 7 ? 'bg-red-800 text-white border-red-800' : 'border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700'"
                        class="px-3 py-1 rounded-full border transition">Todos os dias</button>
                    <button type="button" @click="setDays([2, 3, 4, 5, 6])"
                        :class="daysAre([2, 3, 4, 5, 6]) ? 'bg-red-800 text-white border-red-800' : 'border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700'"
                        class="px-3 py-1 rounded-full border transition">Segunda a sexta</button>
                    <button type="button" @click="setDays([1, 7])"
                        :class="daysAre([1, 7]) ? 'bg-red-800 text-white border-red-800' : 'border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700'"
                        class="px-3 py-1 rounded-full border transition">Fim de semana</button>
                </div>
                <div class="grid grid-cols-7 gap-1.5">
                    @foreach($weekdays as $day)
                        <label class="cursor-pointer">
                            <input type="checkbox" name="weekdays[]" value="{{ $day->id }}" x-model="weekdays" class="peer sr-only">
                            <span class="flex items-center justify-center py-2 rounded-lg border text-xs font-bold capitalize transition select-none
                                border-gray-200 dark:border-gray-600 text-gray-500 dark:text-gray-400 hover:border-gray-300
                                peer-checked:bg-red-800 peer-checked:text-white peer-checked:border-red-800
                                peer-focus-visible:ring-2 peer-focus-visible:ring-red-800">
                                {{ $day->short_name_pt }}
                            </span>
                        </label>
                    @endforeach
                </div>
                <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400" x-show="weekdays.length === 0">Nenhum dia marcado vale para todos os dias.</p>
            </fieldset>

            {{-- 3. Onde --}}
            <fieldset>
                <div class="flex items-center justify-between mb-2">
                    <legend class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-300">
                        <span class="w-5 h-5 rounded-full bg-gray-900 dark:bg-white text-white dark:text-gray-900 text-[11px] font-bold flex items-center justify-center">3</span>
                        Quais contatores
                    </legend>
                    @if($contactors->count() > 1)
                        <button type="button" @click="toggleAllContactors()" class="text-xs font-semibold text-red-800 dark:text-red-400 hover:underline"
                            x-text="contactors.length === allContactors().length ? 'Limpar seleção' : 'Selecionar todos'"></button>
                    @endif
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-52 overflow-y-auto pr-1">
                    @foreach($contactors as $c)
                        <label class="flex items-start gap-3 px-3 py-2.5 rounded-xl border cursor-pointer transition"
                            :class="contactors.includes('{{ $c->id }}') ? 'border-red-800 bg-red-50/60 dark:bg-red-900/20' : 'border-gray-200 dark:border-gray-600 hover:border-gray-300 dark:hover:border-gray-500'">
                            <input type="checkbox" name="contactors[]" value="{{ $c->id }}" x-model="contactors"
                                class="mt-0.5 rounded border-gray-300 dark:border-gray-600 text-red-800 focus:ring-red-800">
                            <span class="min-w-0">
                                <span class="block text-sm font-medium text-gray-800 dark:text-gray-200 truncate">{{ $c->name }}</span>
                                <span class="block text-[11px] text-gray-400 dark:text-gray-500 truncate">
                                    {{ $c->places->isNotEmpty() ? $c->places->pluck('name')->join(', ') : $c->entity_id }}
                                </span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            {{-- Avançado --}}
            <div class="rounded-xl border border-gray-200 dark:border-gray-600">
                <button type="button" @click="advanced = !advanced" :aria-expanded="advanced"
                    class="w-full flex items-center justify-between px-4 py-3 text-sm font-semibold text-gray-700 dark:text-gray-300">
                    <span>Período, prioridade e status</span>
                    <svg class="w-4 h-4 text-gray-400 transition-transform" :class="advanced && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="advanced" x-cloak class="px-4 pb-4 space-y-4 border-t border-gray-100 dark:border-gray-700 pt-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label for="{{ $modalId }}-start" class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Começa em</label>
                            <input id="{{ $modalId }}-start" type="date" name="start_date" x-model="startDate" class="{{ $input }}">
                        </div>
                        <div>
                            <label for="{{ $modalId }}-end" class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Termina em</label>
                            <input id="{{ $modalId }}-end" type="date" name="end_date" x-model="endDate" :min="startDate" class="{{ $input }}">
                        </div>
                    </div>
                    <p class="-mt-2 text-xs text-gray-500 dark:text-gray-400">Deixe em branco para valer sem data de início ou de fim.</p>

                    <div class="grid grid-cols-1 sm:grid-cols-[8rem_1fr] gap-3 items-start">
                        <div>
                            <label for="{{ $modalId }}-priority" class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Prioridade</label>
                            <input id="{{ $modalId }}-priority" type="number" name="priority" min="0" max="999" value="{{ $priority }}" class="{{ $input }}">
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 sm:pt-6">
                            Se dois agendamentos valerem ao mesmo tempo para o mesmo contator, vence o de maior número.
                            O controle manual do cartão sempre vence.
                        </p>
                    </div>

                    <label class="flex items-center justify-between gap-3 cursor-pointer">
                        <span>
                            <span class="block text-sm font-medium text-gray-800 dark:text-gray-200">Ativo</span>
                            <span class="block text-xs text-gray-500 dark:text-gray-400">Pausado, o agendamento fica guardado mas não tem efeito.</span>
                        </span>
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" @checked($isActive) class="sr-only peer">
                        <span class="relative w-11 h-6 shrink-0 rounded-full bg-gray-300 dark:bg-gray-600 transition peer-checked:bg-green-500
                            peer-focus-visible:ring-2 peer-focus-visible:ring-red-800
                            after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:w-5 after:h-5 after:rounded-full after:bg-white after:shadow after:transition-all
                            peer-checked:after:translate-x-5"></span>
                    </label>
                </div>
            </div>
        </div>

        {{-- Resumo + ações --}}
        <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900/40 border-t border-gray-100 dark:border-gray-700 space-y-3">
            <p class="flex items-start gap-2 text-sm" :class="summary.ok ? 'text-gray-700 dark:text-gray-300' : 'text-orange-700 dark:text-orange-300'">
                <svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span x-text="summary.ok ? summary.text : 'Selecione ao menos um contator.'"></span>
            </p>
            <div class="flex justify-end gap-3">
                <button type="button" @click="$dispatch('close-modal', '{{ $modalId }}')"
                    class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200/60 dark:hover:bg-gray-700 transition">
                    Cancelar
                </button>
                <button type="submit" :disabled="!summary.ok"
                    class="px-5 py-2 text-sm font-semibold bg-red-800 hover:bg-red-700 text-white rounded-lg shadow-sm transition disabled:opacity-50 disabled:cursor-not-allowed">
                    {{ $override ? 'Salvar alterações' : 'Criar agendamento' }}
                </button>
            </div>
        </div>
    </form>
</x-modal>
