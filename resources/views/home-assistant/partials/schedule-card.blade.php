{{--
    Linha da lista de agendamentos.
    Parâmetros:
      $override  HomeAssistantOverride
      $inEffect  bool  está decidindo o estado de algum contator agora
      $weekdays  Collection  (do escopo da página)
--}}
@php
    [$accent, $iconClass, $icon] = match ($override->mode) {
        'manual_on'  => ['bg-ok', 'bg-ok-soft text-ok',
            'M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z'],
        'manual_off' => ['bg-ink-3', 'bg-line text-ink-2',
            'M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636'],
        default      => ['bg-grena', 'bg-grena-tint text-grena-ink',
            'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
    };

    $selectedDays = $override->weekdays->pluck('id')->all();
    $allDays      = empty($selectedDays) || count($selectedDays) === 7;
    $isExpired    = $override->is_expired;
    $isLive       = $override->is_active && ! $isExpired;

    $period = match (true) {
        $override->start_date && $override->end_date => $override->start_date->format('d/m/Y') . ' a ' . $override->end_date->format('d/m/Y'),
        (bool) $override->start_date                  => 'A partir de ' . $override->start_date->format('d/m/Y'),
        (bool) $override->end_date                    => 'Até ' . $override->end_date->format('d/m/Y'),
        default                                       => null,
    };
@endphp

<article data-search="" class="relative bg-surface rounded-card shadow-card border border-line overflow-hidden {{ $isLive ? '' : 'opacity-75' }}">
    <div class="absolute left-0 inset-y-0 w-1 {{ $isLive ? $accent : 'bg-line' }}"></div>

    <div class="p-4 pl-5 sm:pl-6 flex flex-col lg:flex-row lg:items-center gap-4">
        {{-- Identificação --}}
        <div class="flex items-start gap-3 min-w-0 lg:w-80 shrink-0">
            <span class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 {{ $iconClass }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $icon }}"/></svg>
            </span>
            <div class="min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <h4 class="font-bold text-ink truncate">{{ $override->name ?: 'Agendamento #' . $override->id }}</h4>
                    @if($inEffect)
                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wide bg-ok-soft text-ok">
                            <span class="w-1.5 h-1.5 rounded-full bg-ok animate-pulse"></span>
                            Valendo agora
                        </span>
                    @elseif(! $override->is_active)
                        <span class="px-1.5 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wide bg-line text-ink-2">Pausado</span>
                    @elseif($isExpired)
                        <span class="px-1.5 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wide bg-warn-soft text-warn">Expirado</span>
                    @endif
                </div>
                <p class="text-sm text-ink-2 mt-0.5">{{ $override->summary }}</p>
            </div>
        </div>

        {{-- Detalhes --}}
        <div class="flex-1 min-w-0 flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-ink-2">
            {{-- Dias --}}
            <div class="flex gap-0.5" title="{{ $allDays ? 'Todos os dias' : $weekdays->whereIn('id', $selectedDays)->pluck('name_pt')->join(', ') }}">
                @foreach($weekdays as $day)
                    @php $on = $allDays || in_array($day->id, $selectedDays); @endphp
                    <span class="w-6 h-6 flex items-center justify-center rounded-md text-[10px] font-bold uppercase
                        {{ $on ? 'bg-ink text-canvas' : 'bg-subtle text-ink-3' }}">
                        {{ mb_substr($day->short_name_pt, 0, 1) }}
                    </span>
                @endforeach
            </div>

            {{-- Contatores --}}
            <span class="inline-flex items-center gap-1 min-w-0" title="{{ $override->contactors->pluck('name')->join(', ') }}">
                <svg class="w-3.5 h-3.5 text-ink-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                <span class="truncate max-w-[16rem]">
                    @if($override->contactors->isEmpty())
                        <span class="text-warn">Nenhum contator</span>
                    @elseif($override->contactors->count() <= 2)
                        {{ $override->contactors->pluck('name')->join(', ') }}
                    @else
                        {{ $override->contactors->count() }} contatores
                    @endif
                </span>
            </span>

            @if($period)
                <span class="inline-flex items-center gap-1">
                    <svg class="w-3.5 h-3.5 text-ink-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    {{ $period }}
                </span>
            @endif

            @if($override->priority > 0)
                <span class="px-1.5 py-0.5 rounded-md bg-subtle font-semibold" title="Maior prioridade vence quando dois agendamentos valem ao mesmo tempo">
                    Prioridade {{ $override->priority }}
                </span>
            @endif

            @if($override->creator)
                <span class="text-ink-3">por {{ $override->creator->name }}</span>
            @endif
        </div>

        {{-- Ações --}}
        <div class="flex items-center gap-1 shrink-0 lg:ml-auto">
            <form method="POST" action="{{ route('home-assistant.overrides.toggle', $override) }}">
                @csrf
                <button type="submit" role="switch" aria-checked="{{ $override->is_active ? 'true' : 'false' }}"
                    title="{{ $override->is_active ? 'Pausar' : 'Ativar' }}"
                    class="relative w-11 h-6 rounded-full transition focus:outline-none focus-visible:ring-2 focus-visible:ring-grena-tint
                        {{ $override->is_active ? 'bg-ok' : 'bg-line' }}">
                    <span class="absolute top-0.5 left-0.5 w-5 h-5 rounded-full bg-surface shadow-card transition-transform {{ $override->is_active ? 'translate-x-5' : '' }}"></span>
                    <span class="sr-only">{{ $override->is_active ? 'Pausar' : 'Ativar' }}</span>
                </button>
            </form>
            <button type="button" @click="$dispatch('open-modal', 'schedule-{{ $override->id }}')" title="Editar"
                class="ml-2 p-2 rounded-lg text-ink-2 hover:text-ink hover:bg-subtle transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                <span class="sr-only">Editar</span>
            </button>
            <form method="POST" action="{{ route('home-assistant.overrides.destroy', $override) }}"
                onsubmit="return confirm(@js('Remover o agendamento “' . ($override->name ?: '#' . $override->id) . '”?'))">
                @csrf @method('DELETE')
                <button type="submit" title="Remover"
                    class="p-2 rounded-lg text-ink-3 hover:text-danger hover:bg-danger-soft transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    <span class="sr-only">Remover</span>
                </button>
            </form>
        </div>
    </div>
</article>
