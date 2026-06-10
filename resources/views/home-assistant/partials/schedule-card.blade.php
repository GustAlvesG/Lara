@php
    $modeMeta = match($override->mode) {
        'manual_on'  => ['Forçar ligado',    'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400', 'bg-green-500'],
        'manual_off' => ['Forçar desligado', 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',       'bg-red-500'],
        default      => ['Por horário',      'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',    'bg-blue-500'],
    };
    [$modeLabel, $modeBadge, $accent] = $modeMeta;

    $allWeekdays        = $weekdays ?? collect();
    $selectedWeekdayIds = $override->weekdays->pluck('id')->all();
    $isExpired          = $override->is_expired;

    $weekdaysLabel = empty($selectedWeekdayIds)
        ? 'Todos os dias'
        : $allWeekdays->whereIn('id', $selectedWeekdayIds)->map(fn ($d) => ucfirst($d->short_name_pt))->join(', ');
@endphp

<div x-data="{ open: false }"
    class="relative bg-white dark:bg-gray-800 shadow-sm rounded-2xl border border-gray-100 dark:border-gray-700 overflow-hidden {{ $override->is_active && !$isExpired ? '' : 'opacity-70' }}">
    <div class="absolute left-0 top-0 bottom-0 w-1.5 {{ $accent }}"></div>

    <div class="p-4 pl-6">
        {{-- Topo resumido --}}
        <div class="flex items-center justify-between gap-2">
            <div class="min-w-0">
                <div class="flex items-center gap-1.5 flex-wrap mb-1">
                    <span class="px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider {{ $modeBadge }}">{{ $modeLabel }}</span>
                    @if(!$override->is_active)
                        <span class="px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider bg-gray-200 text-gray-500 dark:bg-gray-700 dark:text-gray-400">Pausado</span>
                    @elseif($isExpired)
                        <span class="px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400">Expirado</span>
                    @endif
                    @if($override->is_quick)
                        <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400">rápido</span>
                    @endif
                </div>
                <h4 class="text-base font-bold text-gray-900 dark:text-white truncate">{{ $override->name ?: 'Agendamento #'.$override->id }}</h4>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5 truncate">
                    {{ $override->contactors->count() }} {{ $override->contactors->count() == 1 ? 'local' : 'locais' }}
                    · {{ $weekdaysLabel }}@if($override->mode === 'schedule_override') · {{ $override->windows->count() }} janela(s) @endif
                </p>
            </div>
            <button @click="open = !open"
                class="shrink-0 inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-semibold text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                Detalhes
                <svg class="w-3.5 h-3.5 transition-transform" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
        </div>

        {{-- Detalhes (expansível) --}}
        <div x-show="open" x-cloak x-transition class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-700 space-y-3">

            {{-- Prioridade --}}
            <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-gray-50 text-gray-500 dark:bg-gray-700/50 border border-gray-100 dark:border-gray-600">Prioridade {{ $override->priority }}</span>
            </div>

            {{-- Janelas --}}
            @if($override->mode === 'schedule_override' && $override->windows->isNotEmpty())
                <div class="flex flex-wrap gap-1.5">
                    @foreach($override->windows as $w)
                        @php $isOn = ($w->state ?? 'on') === 'on'; @endphp
                        <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-lg text-xs font-bold
                            {{ $isOn
                                ? 'bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-300'
                                : 'bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ $isOn ? 'bg-green-500' : 'bg-red-500' }}"></span>
                            {{ substr($w->turn_on_at, 0, 5) }} – {{ substr($w->turn_off_at, 0, 5) }}
                            <span class="font-normal opacity-70">{{ $isOn ? 'liga' : 'desliga' }}</span>
                        </span>
                    @endforeach
                </div>
            @endif

            {{-- Dias da semana --}}
            <div class="flex gap-1 items-center">
                @foreach($allWeekdays as $day)
                    @php $on = in_array($day->id, $selectedWeekdayIds) || empty($selectedWeekdayIds); @endphp
                    <div class="w-7 h-7 flex items-center justify-center rounded-md text-[10px] font-black uppercase
                        {{ $on ? 'bg-red-800 text-white' : 'bg-gray-100 text-gray-300 dark:bg-gray-700 dark:text-gray-500' }}">
                        {{ substr($day->short_name_pt, 0, 1) }}
                    </div>
                @endforeach
                @if(empty($selectedWeekdayIds))
                    <span class="ml-1 text-[10px] text-gray-400">todos os dias</span>
                @endif
            </div>

            {{-- Locais --}}
            <div class="flex flex-wrap gap-1.5">
                @foreach($override->contactors as $c)
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-gray-50 dark:bg-gray-700/50 border border-gray-100 dark:border-gray-600 text-[11px] font-semibold text-gray-600 dark:text-gray-300">
                        <svg class="w-2.5 h-2.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                        {{ $c->name }}
                    </span>
                @endforeach
            </div>

            {{-- Vigência --}}
            <div class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                {{ $override->start_date ? $override->start_date->format('d/m/Y') : 'sempre' }}
                <span class="text-gray-300">→</span>
                {{ $override->end_date ? $override->end_date->format('d/m/Y') : '∞' }}
                @if($override->creator)
                    <span class="ml-auto text-gray-400">por {{ $override->creator->name }}</span>
                @endif
            </div>

            {{-- Ações --}}
            <div class="flex items-center gap-2 pt-3 border-t border-gray-100 dark:border-gray-700">
                <button onclick="document.getElementById('modal-edit-schedule-{{ $override->id }}').classList.remove('hidden')"
                    class="flex-1 inline-flex justify-center items-center gap-1 px-3 py-1.5 text-xs font-bold rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600 transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    Editar
                </button>

                <form method="POST" action="{{ route('home-assistant.overrides.toggle', $override) }}">
                    @csrf
                    <button type="submit"
                        class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-bold rounded-lg transition
                            {{ $override->is_active
                                ? 'bg-amber-50 text-amber-700 hover:bg-amber-100 dark:bg-amber-900/20 dark:text-amber-400'
                                : 'bg-green-50 text-green-700 hover:bg-green-100 dark:bg-green-900/20 dark:text-green-400' }}">
                        {{ $override->is_active ? 'Pausar' : 'Ativar' }}
                    </button>
                </form>

                <form method="POST" action="{{ route('home-assistant.overrides.destroy', $override) }}"
                    onsubmit="return confirm('Remover este agendamento?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="p-2 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- Modal de edição --}}
@include('home-assistant.partials.schedule-form', [
    'modalId'    => 'modal-edit-schedule-'.$override->id,
    'action'     => route('home-assistant.overrides.update', $override),
    'method'     => 'PUT',
    'title'      => 'Editar agendamento',
    'override'   => $override,
    'contactors' => $contactors,
    'weekdays'   => $weekdays,
])
