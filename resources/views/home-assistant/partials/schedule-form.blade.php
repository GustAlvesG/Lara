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
        'sky'   => 'border-grena bg-grena-tint ring-1 ring-grena',
        'amber' => 'border-ok bg-ok-soft ring-1 ring-ok',
        'gray'  => 'border-line-strong bg-subtle ring-1 ring-ink-2',
    ];
    $modeIcon = [
        'sky'   => 'bg-grena-tint text-grena-ink',
        'amber' => 'bg-ok-soft text-ok',
        'gray'  => 'bg-line text-ink-2',
    ];

    $input = 'w-full border-line-strong rounded-lg text-sm bg-surface text-ink focus:ring-grena-tint focus:border-grena';
@endphp

<x-modal :name="$modalId" :show="$useOld && $errors->any()" maxWidth="2xl">
    <form method="POST" action="{{ $override ? route('home-assistant.overrides.update', $override) : route('home-assistant.overrides.store') }}"
        x-data="scheduleForm(@js($config))">
        @csrf
        @if($override) @method('PUT') @endif
        <input type="hidden" name="_modal" value="{{ $modalId }}">

        {{-- Cabeçalho --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-line">
            <h3 class="text-lg font-bold text-ink">{{ $override ? 'Editar agendamento' : 'Novo agendamento' }}</h3>
            <button type="button" @click="$dispatch('close-modal', '{{ $modalId }}')" class="p-1 rounded-lg text-ink-3 hover:text-ink-2" aria-label="Fechar">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <div class="px-6 py-5 space-y-6 max-h-[70vh] overflow-y-auto">
            @if($useOld && $errors->any())
                <div class="p-3 rounded-lg bg-danger-soft text-sm text-danger space-y-0.5">
                    @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                </div>
            @endif

            {{-- Nome --}}
            <div>
                <label for="{{ $modalId }}-name" class="block text-sm font-semibold text-ink mb-1">Nome</label>
                <input id="{{ $modalId }}-name" type="text" name="name" required maxlength="255" value="{{ $name }}"
                    placeholder="Ex.: Refletores à noite" class="{{ $input }}">
            </div>

            {{-- 1. O que fazer --}}
            <fieldset>
                <legend class="flex items-center gap-2 text-sm font-semibold text-ink mb-2">
                    <span class="w-5 h-5 rounded-full bg-ink text-canvas text-[11px] font-bold flex items-center justify-center">1</span>
                    O que fazer
                </legend>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                    @foreach($modes as $value => [$label, $description, $color, $icon])
                        <label class="relative flex sm:flex-col gap-3 sm:gap-2 p-3 rounded-xl border cursor-pointer transition"
                            :class="mode === '{{ $value }}' ? '{{ $modeActive[$color] }}' : 'border-line hover:border-line-strong'">
                            <input type="radio" name="mode" value="{{ $value }}" x-model="mode" class="sr-only">
                            <span class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 {{ $modeIcon[$color] }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $icon }}"/></svg>
                            </span>
                            <span>
                                <span class="block text-sm font-semibold text-ink">{{ $label }}</span>
                                <span class="block text-xs text-ink-2 mt-0.5">{{ $description }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            {{-- 1b. Faixas de horário --}}
            <div x-show="mode === 'schedule_override'" x-cloak class="space-y-2 -mt-2 p-4 rounded-xl bg-grena-tint/40 border border-grena/30">
                <p class="text-sm font-semibold text-ink">Faixas de horário</p>

                <template x-for="(w, i) in windows" :key="i">
                    <div class="flex flex-wrap sm:flex-nowrap items-center gap-2">
                        <input type="hidden" :name="`windows[${i}][state]`" :value="w.state" :disabled="mode !== 'schedule_override'">
                        <div class="flex rounded-lg p-0.5 bg-surface border border-line text-xs font-semibold shrink-0">
                            <button type="button" @click="w.state = 'on'"
                                :class="w.state === 'on' ? 'bg-ok text-white dark:text-canvas' : 'text-ink-2'"
                                class="px-3 py-1.5 rounded-md transition">Liga</button>
                            <button type="button" @click="w.state = 'off'"
                                :class="w.state === 'off' ? 'bg-ink text-canvas' : 'text-ink-2'"
                                class="px-3 py-1.5 rounded-md transition">Desliga</button>
                        </div>
                        <span class="text-sm text-ink-2">das</span>
                        <input type="time" x-model="w.turn_on_at" :name="`windows[${i}][turn_on_at]`" required
                            :disabled="mode !== 'schedule_override'"
                            class="w-28 border-line-strong rounded-lg text-sm bg-surface text-ink focus:ring-grena-tint focus:border-grena">
                        <span class="text-sm text-ink-2">às</span>
                        <input type="time" x-model="w.turn_off_at" :name="`windows[${i}][turn_off_at]`" required
                            :disabled="mode !== 'schedule_override'"
                            class="w-28 border-line-strong rounded-lg text-sm bg-surface text-ink focus:ring-grena-tint focus:border-grena">
                        <span x-show="crossesMidnight(w)" class="text-[11px] font-medium text-grena-ink whitespace-nowrap">+1 dia</span>
                        <button type="button" @click="removeWindow(i)" x-show="windows.length > 1" aria-label="Remover faixa"
                            class="ml-auto p-1.5 rounded-lg text-ink-3 hover:text-danger hover:bg-danger-soft transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </template>

                <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
                    <button type="button" @click="addWindow()"
                        class="inline-flex items-center gap-1 text-sm font-semibold text-grena-ink hover:underline">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Adicionar faixa
                    </button>
                    <p class="text-xs text-ink-2">Faixas podem passar da meia-noite (22:00 às 02:00).</p>
                </div>
            </div>

            {{-- 2. Quando --}}
            <fieldset>
                <legend class="flex items-center gap-2 text-sm font-semibold text-ink mb-2">
                    <span class="w-5 h-5 rounded-full bg-ink text-canvas text-[11px] font-bold flex items-center justify-center">2</span>
                    Em quais dias
                </legend>
                <div class="flex flex-wrap gap-2 mb-2 text-xs font-semibold">
                    <button type="button" @click="setDays([])"
                        :class="weekdays.length === 0 || weekdays.length === 7 ? 'bg-grena text-white border-grena' : 'border-line-strong text-ink-2 hover:bg-subtle'"
                        class="px-3 py-1 rounded-full border transition">Todos os dias</button>
                    <button type="button" @click="setDays([2, 3, 4, 5, 6])"
                        :class="daysAre([2, 3, 4, 5, 6]) ? 'bg-grena text-white border-grena' : 'border-line-strong text-ink-2 hover:bg-subtle'"
                        class="px-3 py-1 rounded-full border transition">Segunda a sexta</button>
                    <button type="button" @click="setDays([1, 7])"
                        :class="daysAre([1, 7]) ? 'bg-grena text-white border-grena' : 'border-line-strong text-ink-2 hover:bg-subtle'"
                        class="px-3 py-1 rounded-full border transition">Fim de semana</button>
                </div>
                <div class="grid grid-cols-7 gap-1.5">
                    @foreach($weekdays as $day)
                        <label class="cursor-pointer">
                            <input type="checkbox" name="weekdays[]" value="{{ $day->id }}" x-model="weekdays" class="peer sr-only">
                            <span class="flex items-center justify-center py-2 rounded-lg border text-xs font-bold capitalize transition select-none
                                border-line text-ink-2 hover:border-line-strong
                                peer-checked:bg-grena peer-checked:text-white peer-checked:border-grena
                                peer-focus-visible:ring-2 peer-focus-visible:ring-grena-tint">
                                {{ $day->short_name_pt }}
                            </span>
                        </label>
                    @endforeach
                </div>
                <p class="mt-1.5 text-xs text-ink-2" x-show="weekdays.length === 0">Nenhum dia marcado vale para todos os dias.</p>
            </fieldset>

            {{-- 3. Onde --}}
            <fieldset>
                <div class="flex items-center justify-between mb-2">
                    <legend class="flex items-center gap-2 text-sm font-semibold text-ink">
                        <span class="w-5 h-5 rounded-full bg-ink text-canvas text-[11px] font-bold flex items-center justify-center">3</span>
                        Quais contatores
                    </legend>
                    @if($contactors->count() > 1)
                        <button type="button" @click="toggleAllContactors()" class="text-xs font-semibold text-grena-ink hover:underline"
                            x-text="contactors.length === allContactors().length ? 'Limpar seleção' : 'Selecionar todos'"></button>
                    @endif
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-52 overflow-y-auto pr-1">
                    @foreach($contactors as $c)
                        <label class="flex items-start gap-3 px-3 py-2.5 rounded-xl border cursor-pointer transition"
                            :class="contactors.includes('{{ $c->id }}') ? 'border-grena bg-grena-tint/60' : 'border-line hover:border-line-strong'">
                            <input type="checkbox" name="contactors[]" value="{{ $c->id }}" x-model="contactors"
                                class="mt-0.5 rounded border-line-strong text-grena-ink focus:ring-grena-tint">
                            <span class="min-w-0">
                                <span class="block text-sm font-medium text-ink truncate">{{ $c->name }}</span>
                                <span class="block text-[11px] text-ink-3 truncate">
                                    {{ $c->places->isNotEmpty() ? $c->places->pluck('name')->join(', ') : $c->entity_id }}
                                </span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            {{-- Avançado --}}
            <div class="rounded-xl border border-line">
                <button type="button" @click="advanced = !advanced" :aria-expanded="advanced"
                    class="w-full flex items-center justify-between px-4 py-3 text-sm font-semibold text-ink">
                    <span>Período, prioridade e status</span>
                    <svg class="w-4 h-4 text-ink-3 transition-transform" :class="advanced && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="advanced" x-cloak class="px-4 pb-4 space-y-4 border-t border-line pt-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label for="{{ $modalId }}-start" class="block text-xs font-semibold text-ink-2 mb-1">Começa em</label>
                            <input id="{{ $modalId }}-start" type="date" name="start_date" x-model="startDate" class="{{ $input }}">
                        </div>
                        <div>
                            <label for="{{ $modalId }}-end" class="block text-xs font-semibold text-ink-2 mb-1">Termina em</label>
                            <input id="{{ $modalId }}-end" type="date" name="end_date" x-model="endDate" :min="startDate" class="{{ $input }}">
                        </div>
                    </div>
                    <p class="-mt-2 text-xs text-ink-2">Deixe em branco para valer sem data de início ou de fim.</p>

                    <div class="grid grid-cols-1 sm:grid-cols-[8rem_1fr] gap-3 items-start">
                        <div>
                            <label for="{{ $modalId }}-priority" class="block text-xs font-semibold text-ink-2 mb-1">Prioridade</label>
                            <input id="{{ $modalId }}-priority" type="number" name="priority" min="0" max="999" value="{{ $priority }}" class="{{ $input }}">
                        </div>
                        <p class="text-xs text-ink-2 sm:pt-6">
                            Se dois agendamentos valerem ao mesmo tempo para o mesmo contator, vence o de maior número.
                            O controle manual do cartão sempre vence.
                        </p>
                    </div>

                    <label class="flex items-center justify-between gap-3 cursor-pointer">
                        <span>
                            <span class="block text-sm font-medium text-ink">Ativo</span>
                            <span class="block text-xs text-ink-2">Pausado, o agendamento fica guardado mas não tem efeito.</span>
                        </span>
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" @checked($isActive) class="sr-only peer">
                        <span class="relative w-11 h-6 shrink-0 rounded-full bg-line transition peer-checked:bg-ok
                            peer-focus-visible:ring-2 peer-focus-visible:ring-grena-tint
                            after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:w-5 after:h-5 after:rounded-full after:bg-white after:shadow after:transition-all
                            peer-checked:after:translate-x-5"></span>
                    </label>
                </div>
            </div>
        </div>

        {{-- Resumo + ações --}}
        <div class="px-6 py-4 bg-subtle border-t border-line space-y-3">
            <p class="flex items-start gap-2 text-sm" :class="summary.ok ? 'text-ink' : 'text-warn'">
                <svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span x-text="summary.ok ? summary.text : 'Selecione ao menos um contator.'"></span>
            </p>
            <div class="flex justify-end gap-3">
                <button type="button" @click="$dispatch('close-modal', '{{ $modalId }}')"
                    class="px-4 py-2 text-sm font-medium text-ink rounded-lg hover:bg-subtle transition">
                    Cancelar
                </button>
                <button type="submit" :disabled="!summary.ok"
                    class="px-5 py-2 text-sm font-semibold bg-grena hover:bg-grena-hover text-white rounded-lg shadow-card transition disabled:opacity-50 disabled:cursor-not-allowed">
                    {{ $override ? 'Salvar alterações' : 'Criar agendamento' }}
                </button>
            </div>
        </div>
    </form>
</x-modal>
