{{--
    Formulário reutilizável de agendamento (criar / editar).
    Parâmetros:
      $modalId    string  id único do modal
      $action     string  URL do form
      $method     string  'POST' (create) | 'PUT' (edit)
      $title      string  título do modal
      $override   ?Model  agendamento ao editar (null ao criar)
      $contactors Collection
      $weekdays   Collection
--}}
@php
    $winData = $override
        ? $override->windows->map(fn ($w) => [
            'turn_on_at'  => substr($w->turn_on_at, 0, 5),
            'turn_off_at' => substr($w->turn_off_at, 0, 5),
            'state'       => $w->state ?? 'on',
        ])->values()->all()
        : [];
    $selectedContactors = $override ? $override->contactors->pluck('id')->all() : [];
    $selectedWeekdays   = $override ? $override->weekdays->pluck('id')->all() : [];
    $currentMode        = $override->mode ?? 'schedule_override';
@endphp

<div id="{{ $modalId }}" class="hidden fixed inset-0 bg-black/50 z-50 flex items-start justify-center p-4 overflow-y-auto">
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl w-full max-w-2xl my-8">

        {{-- Cabeçalho --}}
        <div class="flex items-center justify-between p-5 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ $title }}</h3>
            <button type="button" onclick="document.getElementById('{{ $modalId }}').classList.add('hidden')"
                class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <form method="POST" action="{{ $action }}"
            x-data="scheduleForm({ mode: '{{ $currentMode }}', windows: @js($winData) })"
            class="p-5 space-y-5 max-h-[75vh] overflow-y-auto">
            @csrf
            @if($method === 'PUT') @method('PUT') @endif

            {{-- Nome --}}
            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Nome do agendamento</label>
                <input type="text" name="name" required value="{{ $override->name ?? '' }}"
                    placeholder="Ex: Iluminação fim de semana"
                    class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-red-800 focus:border-transparent">
            </div>

            {{-- Modo --}}
            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Ação</label>
                <input type="hidden" name="mode" :value="mode">
                <div class="grid grid-cols-3 gap-2">
                    <button type="button" @click="mode='manual_on'"
                        :class="mode==='manual_on' ? 'bg-green-600 text-white border-green-600' : 'bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-300 border-gray-300 dark:border-gray-600'"
                        class="px-3 py-2.5 rounded-lg border text-xs font-bold transition flex flex-col items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Forçar ligado
                    </button>
                    <button type="button" @click="mode='manual_off'"
                        :class="mode==='manual_off' ? 'bg-red-600 text-white border-red-600' : 'bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-300 border-gray-300 dark:border-gray-600'"
                        class="px-3 py-2.5 rounded-lg border text-xs font-bold transition flex flex-col items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        Forçar desligado
                    </button>
                    <button type="button" @click="mode='schedule_override'"
                        :class="mode==='schedule_override' ? 'bg-blue-600 text-white border-blue-600' : 'bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-300 border-gray-300 dark:border-gray-600'"
                        class="px-3 py-2.5 rounded-lg border text-xs font-bold transition flex flex-col items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Por horário
                    </button>
                </div>
            </div>

            {{-- Janelas de horário (só no modo "Por horário") --}}
            <div x-show="mode==='schedule_override'" x-cloak class="space-y-2">
                <div class="flex items-center justify-between">
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300">Janelas de horário</label>
                    <button type="button" @click="addWindow()"
                        class="inline-flex items-center gap-1 text-xs font-semibold text-blue-600 hover:text-blue-700">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Adicionar janela
                    </button>
                </div>
                <template x-for="(w, i) in windows" :key="i">
                    <div class="flex items-end gap-2">

                        {{-- Estado: Ligado / Desligado --}}
                        <div class="shrink-0">
                            <span class="block text-[10px] uppercase tracking-wide text-gray-400 mb-0.5">Estado</span>
                            <input type="hidden" :name="`windows[${i}][state]`" :value="w.state">
                            <div class="flex rounded-lg border overflow-hidden border-gray-300 dark:border-gray-600 text-xs font-bold">
                                <button type="button"
                                    @click="w.state = 'on'"
                                    :class="w.state === 'on'
                                        ? 'bg-green-600 text-white'
                                        : 'bg-white dark:bg-gray-700 text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-600'"
                                    class="px-3 py-2 transition">
                                    Ligado
                                </button>
                                <button type="button"
                                    @click="w.state = 'off'"
                                    :class="w.state === 'off'
                                        ? 'bg-red-600 text-white'
                                        : 'bg-white dark:bg-gray-700 text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-600'"
                                    class="px-3 py-2 border-l border-gray-300 dark:border-gray-600 transition">
                                    Desligado
                                </button>
                            </div>
                        </div>

                        {{-- Horários --}}
                        <div class="flex-1 grid grid-cols-2 gap-2">
                            <div>
                                <span class="block text-[10px] uppercase tracking-wide text-gray-400 mb-0.5">De</span>
                                <input type="time" x-model="w.turn_on_at" :name="`windows[${i}][turn_on_at]`"
                                    class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>
                            <div>
                                <span class="block text-[10px] uppercase tracking-wide text-gray-400 mb-0.5">Até</span>
                                <input type="time" x-model="w.turn_off_at" :name="`windows[${i}][turn_off_at]`"
                                    class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>
                        </div>

                        {{-- Remover --}}
                        <button type="button" @click="removeWindow(i)" x-show="windows.length > 1"
                            class="p-2 text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </div>
                </template>
                <p class="text-xs text-gray-400 dark:text-gray-500">Janelas que cruzam a meia-noite são suportadas (ex.: 22:00 → 02:00).</p>
            </div>

            {{-- Dias da semana --}}
            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                    Dias da semana
                    <span class="font-normal text-xs text-gray-400">(nenhum marcado = todos os dias)</span>
                </label>
                <div class="flex flex-wrap gap-2">
                    @foreach($weekdays as $day)
                        <label class="cursor-pointer">
                            <input type="checkbox" name="weekdays[]" value="{{ $day->id }}" class="peer sr-only"
                                @if(in_array($day->id, $selectedWeekdays)) checked @endif>
                            <span class="inline-flex items-center justify-center w-12 py-1.5 rounded-lg border text-xs font-bold capitalize transition
                                         border-gray-300 dark:border-gray-600 text-gray-500 dark:text-gray-400
                                         peer-checked:bg-red-800 peer-checked:text-white peer-checked:border-red-800">
                                {{ $day->short_name_pt }}
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- Locais (contactors) --}}
            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Locais</label>
                @if($contactors->isEmpty())
                    <p class="text-xs text-gray-400">Nenhum contator cadastrado.</p>
                @else
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-40 overflow-y-auto pr-1">
                        @foreach($contactors as $c)
                            <label class="flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-600 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                                <input type="checkbox" name="contactors[]" value="{{ $c->id }}"
                                    class="rounded border-gray-300 text-red-800 focus:ring-red-800"
                                    @if(in_array($c->id, $selectedContactors)) checked @endif>
                                <span class="text-sm text-gray-700 dark:text-gray-300 truncate">
                                    {{ $c->name }}
                                    <span class="text-xs text-gray-400 font-mono">{{ $c->entity_id }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Vigência + prioridade --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Início <span class="font-normal text-xs text-gray-400">(opcional)</span></label>
                    <input type="date" name="start_date"
                        value="{{ optional($override?->start_date)->format('Y-m-d') }}"
                        class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-red-800 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Fim <span class="font-normal text-xs text-gray-400">(opcional)</span></label>
                    <input type="date" name="end_date"
                        value="{{ optional($override?->end_date)->format('Y-m-d') }}"
                        class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-red-800 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Prioridade</label>
                    <input type="number" name="priority" min="0" max="999"
                        value="{{ $override->priority ?? 0 }}"
                        class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-red-800 focus:border-transparent">
                </div>
            </div>

            {{-- Ativo --}}
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="is_active" value="1"
                    @if(!$override || $override->is_active) checked @endif
                    class="rounded border-gray-300 text-red-800 focus:ring-red-800">
                <span class="text-sm text-gray-700 dark:text-gray-300">Agendamento ativo</span>
            </label>

            {{-- Ações --}}
            <div class="flex justify-end gap-3 pt-2 border-t border-gray-100 dark:border-gray-700">
                <button type="button" onclick="document.getElementById('{{ $modalId }}').classList.add('hidden')"
                    class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">
                    Cancelar
                </button>
                <button type="submit"
                    class="px-5 py-2 text-sm bg-red-800 hover:bg-red-700 text-white rounded-lg font-semibold">
                    Salvar agendamento
                </button>
            </div>
        </form>
    </div>
</div>
